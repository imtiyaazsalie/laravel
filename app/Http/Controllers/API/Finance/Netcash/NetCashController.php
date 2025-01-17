<?php

namespace App\Http\Controllers\API\Finance\Netcash;

use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentProcessorTag;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\TenantUser;
use App\Models\UserInvoice;
use App\Services\CrmService;
use App\Services\InvoiceService;
use App\Services\NonceService;
use App\Services\WidgetService;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;

class NetCashController extends Controller
{
    public function __construct(private readonly CrmService $crmService, private readonly WidgetService $widgetService)
    {
    }

    public function accept()
    {
        return redirect(config('octiv.web_app_url').'/payment/success');
    }

    public function decline(Request $request)
    {
        // This could be an id or nonce
        $reference = $request->get('reference');

        // Check if id or nonce. The nonce contains '-' and check if nonce is valid
        if (str_contains($reference, '-')) {
            $invoiceId = (new NonceService())->getInvoiceIdFromNonce($reference);

            if (! $invoiceId) {
                return redirect(config('octiv.web_app_url').'/payment/invoice-not-found');
            }
        } else {
            $invoiceId = $reference;
        }

        $invoice = UserInvoice::find($invoiceId);

        if (! $invoice || $invoice->deleted) {
            return redirect(config('octiv.web_app_url').'/payment/invoice-not-found');
        }

        $invoice->update(['last_status_change_reason' => $request->get('Reason')]);

        if ($invoice->discriminator === InvoiceDiscriminator::SIGN_UP_INVOICE) {
            $this->widgetService->completeSignUp($invoice->locationUser->user_id, $invoice->locationUser->location->tenant_id, false);
        } elseif ($invoice->discriminator === InvoiceDiscriminator::DROP_IN_INVOICE) {
            $this->widgetService->completeDropIn($invoice, false);
        } else {

            $content = Markdown::parse(
                view('emails.payment-declined-member', [
                    'invoiceCode' => $invoice->code,
                    'authorizePaymentLink' => config('octiv.web_app_url').'/payment/'.$invoice->getKey().'?gid='.$invoice->facility_to_payment_gateway_id,
                ])
            )->__toString();

            $mailRecipient = $invoice->userLocation?->user?->email ?: $invoice->userLocation?->leadMember?->user?->email;

            $this->crmService->createScheduledEmail(
                content: $content,
                subject: 'Octiv Payment Declined',
                to: $mailRecipient,
            );

            $content = Markdown::parse(
                view('emails.payment-declined-box', [
                    'memberName' => $invoice->userLocation->user?->name ?: $invoice->userLocation->leadMember->user->name,
                    'invoiceCode' => $invoice->code,
                    'invoiceLink' => config('octiv.web_app_url').'/invoices?search='.$invoice->code,
                ])
            )->__toString();

            $location = $invoice->userLocation->location;
            $owner = TenantUser::query()->active()->headCoaches()->where('box_id', $location->tenant_id)->first();
            $toReplyToOrOwner = $location->crmSettings?->reply_to ? $location->crmSettings->reply_to : $owner->user->email;

            $this->crmService->createScheduledEmail(
                content: $content,
                subject: 'Octiv Payment Declined',
                to: $toReplyToOrOwner,
            );
        }

        return redirect(config('octiv.web_app_url').'/payment/declined');
    }

    public function notify(Request $request)
    {
        // e.g POST data: TransactionAccepted=true&Reason=Success&CardHolderIpAddr=164.160.81.49&RequestTrace=57830.123098573&Reference=1017275&Extra1=1017275&Extra2=&Extra3=&Amount=689.78&Method=1

        // This could be an id or nonce
        $reference = $request->get('reference');

        // Check if id or nonce. The nonce contains '-' and check if nonce is valid
        if (str_contains($reference, '-')) {
            $invoiceId = (new NonceService())->getInvoiceIdFromNonce($reference);

            if (! $invoiceId) {
                return redirect(config('octiv.web_app_url').'/payment/invoice-not-found');
            }
        } else {
            $invoiceId = $reference;
        }

        $invoice = UserInvoice::find($invoiceId);

        if (! $invoice || $invoice->deleted) {
            return redirect(config('octiv.web_app_url').'/payment/invoice-not-found');
        }

        if ($request->get('transaction_accepted') === 'true') {
            if ($invoice->status === InvoiceStatus::PAID) {
                return null;
            }

            $paymentReference = 'PayNow payment for invoice # '.$invoice->code;
            $tagIds = [Tag::query()->paymentTag(PaymentProcessorTag::PAY_NOW)->first()->getKey()];
            (new InvoiceService())->createPaymentForInvoice($invoice, InvoicePaymentType::ADHOC, $request->get('amount'), null, $paymentReference, $tagIds);
        } else {
            $invoice->update([
                'status' => InvoiceStatus::UNPAID,
                'last_status_change_reason' => 'PayNow payment for invoice # '.$invoice->code.' has failed due to: '.$request->get('reason'),
            ]);
        }

        return response()->noContent(200);
    }
}
