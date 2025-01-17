<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title }}</title>

    <style type="text/css">
        html, body {
            display: block;
        }

        body {
            font-size: 12px;
            line-height: 150%;
            font-family: Helvetica, serif;
            color: #636366;
        }

        .align-center {
            width: 100% !important;
            display: block !important;
            text-align: center !important;
        }

        .invoice-container {
            max-width: 800px;
            margin: auto;
            padding: 24px;
            border-radius: 8px;
            border: 1px solid #e5e5ea;
        }

        .invoice-container table {
            width: 100%;
            line-height: inherit;
            text-align: left;
        }

        .invoice-container table td {
            padding: 4px;
            vertical-align: top;
        }

        .invoice-container table tr td:last-child {
            text-align: right;
        }

        .studio-logo {
            max-width: 200px;
            max-height: 125px;
        }

        .invoice-content {
            margin-top: 16px;
        }

        .invoice-content .heading td {
            background-color: #e5e5ea;
            font-weight: bold;
        }

        .invoice-content .item td {
            border-bottom: 1px solid #e5e5ea;
        }

        .invoice-content .total td {
            border-top: 1px solid #e5e5ea;
            font-weight: bold;
        }

        .octiv-logo {
            margin-top: 8px;
            max-width: 150px;
        }

        @media only screen and (max-width: 600px) {
            .align-center-small {
                width: 100% !important;
                display: block !important;
                text-align: center !important;
            }
        }

        @media print {
            body {
                color: black;
            }
        }
    </style>

</head>
<body>
    {{ $slot }}
</body>
</html>
