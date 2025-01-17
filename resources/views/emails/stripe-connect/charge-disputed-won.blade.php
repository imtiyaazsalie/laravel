#### Dear {{ $ownerName }},

<br />

I’m happy to inform you that the recent dispute regarding the charge from {{ $memberName }} has been resolved in your favor. Stripe has reviewed the case and decided to close the dispute, confirming that the charge was valid.

<br />

**Dispute Details:**

<br />

**Member Name:** {{ $memberName }} <br />
**Member Email:** {{ $memberEmail }} <br />
**Transaction Date:** {{ $date }} <br />
**Transaction Amount:** {{ $currency }} {{ $amount }} <br />
**Dispute Reason:** {{ $reason }} <br />
**Dispute status:** {{ $status }} <br />

<br />

<a href="{{$link}}" target="_blank">View Dispute Details</a>

<br />

No further action is required from your side, and the disputed amount will remain in your account.
