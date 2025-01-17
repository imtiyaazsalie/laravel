#### Dear {{ $ownerName }},

<br />

I’m writing to inform you that the recent dispute logged by {{ $memberName }} regarding a charge on their account has been resolved, and unfortunately, the case has been decided in their favor. As a result, the disputed amount has been refunded to the client.

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

While this is the final resolution of this specific case, you are welcome to review the full dispute details and assess any preventive measures that could help avoid such cases in the future. You can access the dispute information using the following link:

<br />

<a href="{{$link}}" target="_blank">View Dispute Details</a>
