<?php

namespace Database\Seeders;

use App\Traits\SeedsNotifications;
use Illuminate\Database\Seeder;

class DiscoveryNotificationsSeeder extends Seeder
{
    use SeedsNotifications;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->upsertNotification(
            context: 'discovery_booking_confirmation_member',
            name: 'Discovery Vitality Class Booking - Member',
            subject: '[class_name] booking confirmed',
            description: 'Sent to members. Occurs when a Discovery Vitality user makes a booking',
            content: <<<'HEREA'
                <h3>Exercise anywhere with Vitality Fitness</h3>

                Hi <strong>[member_name]</strong>,
                <br />
                <br />
                You've successfully made a booking on Vitality Fitness. Let's get you ready to smash your class and <font style="color: #ff1c77">earn 100 Vitality points</font> for your workout.
                <br />
                <br />

                <strong style="color: #ff1c77">Details</strong><br />
                Fitness facility: [location_name]<br />
                Class time: [class_time]<br />
                Class date: [class_date]<br />
                Contact details: [location_contact_details]<br />
                Physical address: [location_address]<br />
                <br />

                <strong style="color: #ff1c77">How to access the facility</strong>
                <br /><br />
                When you arrive at the facility, please present your booking confirmation screen that can be found by logging in to the <font style="color: #ff1c77">Discovery App > Vitality Fitness > Upcoming Bookings</font> or simply present this email confirmation.
                <br /><br />

                <strong style="color: #ff1c77">How to earn Vitality points</strong>
                <ol>
                <li><font style="color: #ff1c77">Scan the QR code</font> to check-in and out of the facility to <font style="color: #ff1c77">earn 100 points</font></li>
                <li><font style="color: #ff1c77">Scan the QR code</font> and follow the prompts to <font style="color: #ff1c77">check in</font></li>
                <li><font style="color: #ff1c77">Scan the QR code</font> again to <font style="color: #ff1c77">check out</font> and earn your Vitality points</li>
                </ol>

                <h4 style="color: #ff1c77">OR</h4>

                <strong style="color: #ff1c77">Link your device to earn up to 300 points</strong>
                <br /><br />
                You can earn up to 300 Vitality points for tracking heart rate workouts through your linked wearable device. <a href="https://www.discovery.co.za/portal/vitality/vitality-points-tracker">Earn and track Vitality points - Discovery to learn more.</a> Vitality points may take up to 48 hours to reflect.
                <br /><br />
                To view your booking, log in to your Discovery app and scroll down to <font style="color: #ff1c77">Vitality Fitness > Upcoming bookings</font>.
                <br /><br />
                <font style="color: #ff1c77">Live life with Vitality</font>
            HEREA
        );

        $this->upsertNotification(
            context: 'discovery_booking_cancelled_member',
            name: 'Discovery Vitality Class Booking Cancelled - Member',
            subject: '[class_name] booking cancelled',
            description: 'Sent to members. Occurs when a Discovery Vitality user cancels a booking.',
            content: <<<'HEREA'
                <h3>Exercise anywhere with Vitality Fitness</h3>

                Hi <strong>[member_name]</strong>,
                <br />
                <br />
                Your booking has been cancelled. If you cancelled within this facility's cancellation period, you'll receive a single class credit in your Vitality Fitness class packages to use when you book your next class at the same facility.
                <br />
                <br />

                <strong style="color: #ff1c77">Details</strong><br />
                Fitness facility: [location_name]<br />
                Class time: [class_time]<br />
                Class date: [class_date]<br />
                Contact details: [location_contact_details]<br />
                Physical address: [location_address]<br />
                <br />

                When you're ready to use your credit, log in to your Discovery app and scroll down to <font style="color: #ff1c77">Vitality Fitness</font> to book a new class at [location_name] with your single class credit.
                <br /><br />

                <font style="color: #ff1c77">Live life with Vitality</font>
            HEREA
        );

        $this->upsertNotification(
            context: 'discovery_booking_coach_cancelled_member',
            name: 'Discovery Vitality Class Booking Cancelled By Coach - Member',
            subject: '[class_name] booking cancelled',
            description: 'Sent to members. Occurs when a Discovery Vitality coach cancels a booking.',
            content: <<<'HEREA'
                <h3>Exercise anywhere with Vitality Fitness</h3>

                Hi <strong>[member_name]</strong>,
                <br />
                <br />
                Your booking has been cancelled. You'll receive a single class credit in your Vitality Fitness class packages to use when you book your next class at the same facility.
                <br />
                <br />
                Please contact the facility on [location_contact_details] if you have any queries about your cancellation.

                <strong style="color: #ff1c77">Details</strong><br />
                Fitness facility: [location_name]<br />
                Class time: [class_time]<br />
                Class date: [class_date]<br />
                Contact details: [location_contact_details]<br />
                Physical address: [location_address]<br />
                <br />

                When you're ready to use your credit, log in to your Discovery app and scroll down to <font style="color: #ff1c77">Vitality Fitness</font> to book a new class at [location_name] with your single class credit.
                <br /><br />

                <font style="color: #ff1c77">Live life with Vitality</font>
            HEREA
        );

        $this->upsertNotification(
            context: 'discovery_booking_class_discontinued',
            name: 'Discovery Vitality Booking - Class Discontinued - Member',
            subject: '[class_name] discontinued at [location_name]',
            description: 'Sent to Discovery Vitality bookings. Occurs when class is dicontinued.',
            content: <<<'HEREA'
                <h3>Exercise anywhere with Vitality Fitness</h3>

                Hi <strong>[member_name]</strong>,
                <br />
                <br />
                One of the classes you booked on Vitality Fitness has come to an end. You'll receive a single class credit in your Vitality Fitness class packages to use when you book your next class at the same facility.
                <br />
                <br />

                <strong style="color: #ff1c77">Details</strong><br />
                Fitness facility: [location_name]<br />
                Class date: [class_date]<br />
                Contact details: [location_contact_details]<br />
                Physical address: [location_address]<br />
                <br />

                When you're ready, log in to your Discovery app and scroll down to <font style="color: #ff1c77">Vitality Fitness</font> to discover more exciting classes at [location_name].
                <br /><br />

                <font style="color: #ff1c77">Live life with Vitality</font>
            HEREA
        );

        $this->upsertNotification(
            context: 'discovery_booking_modified_member',
            name: 'Discovery Vitality Booking - Class Modified - Member',
            subject: '[class_name] booking modified for [location_name]',
            description: 'Sent to Discovery Vitality bookings. Occurs when class is dicontinued.',
            content: <<<'HEREA'
                <h3>Exercise anywhere with Vitality Fitness</h3>

                Hi <strong>[member_name]</strong>,
                <br />
                <br />
                One of the classes that you booked on Vitality Fitness has been updated. To view your booking, log in to your Discovery app and go to <font style="color: #ff1c77">Vitality Fitness > Upcoming bookings</font>.
                <br />
                <br />

                <strong style="color: #ff1c77">Details</strong><br />
                Fitness facility: [location_name]<br />
                Old time: [old_class_time]<br />
                New time: [class_time]<br />
                Class date: [class_date]<br />
                Contact details: [location_contact_details]<br />
                Physical address: [location_address]<br />
                <br />

                Can’t make it? You can cancel your booking on Vitality Fitness in the Discovery app. If you cancel within the facility’s cancellation period, you’ll receive a single class credit to book a new class at this facility.
                <br /><br />

                <font style="color: #ff1c77">Live life with Vitality</font>
            HEREA
        );

        $this->upsertNotification(
            context: 'discovery_booking_confirmation_coach',
            name: 'Discovery Vitality Class Booking Confirmed - Coach',
            subject: '[class_name] booked by [member_name] [member_surname]',
            description: 'Sent to coach. Occurs when a Discovery Vitality user books a class.',
            content: <<<'HEREA'
                <h3>Exercise anywhere with Vitality Fitness</h3>

                Hi Coach,
                <br />
                <br />
                [member_name] [member_surname] has made a booking at your facility.
                <br />
                <br />

                <strong style="color: #ff1c77">Details</strong><br />
                Class time: [class_time]<br />
                Class date: [class_date]<br />
                <br />

                <font style="color: #ff1c77">Live life with Vitality</font>
            HEREA
        );

        $this->upsertNotification(
            context: 'discovery_booking_cancelled_coach',
            name: 'Discovery Vitality Class Booking Cancelled - Coach',
            subject: '[class_name] cancelled by [member_name] [member_surname]',
            description: 'Sent to coach. Occurs when a Discovery Vitality user cancels a class booking.',
            content: <<<'HEREA'
                <h3>Exercise anywhere with Vitality Fitness</h3>

                Hi Coach,
                <br />
                <br />
                [member_name] [member_surname] has cancelled their booking at your facility.
                <br />
                <br />

                <strong style="color: #ff1c77">Details</strong><br />
                Class time: [class_time]<br />
                Class date: [class_date]<br />
                <br />
                <br />

                They will receive a single class credit to book another class at your facility through <font style="color: #ff1c77">Vitality Fitness</font> in the Discovery app.

                <font style="color: #ff1c77">Live life with Vitality</font>
            HEREA
        );
    }
}
