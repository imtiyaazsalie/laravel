<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Octiv</title>

    <style type="text/css">
        /* Client-specific Styles */
        #outlook a {
            padding: 0;
        }

        /* Reset Styles */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            -webkit-text-size-adjust: none;
        }

        img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        table td {
            border-collapse: collapse;
        }

        /* Styles */
        #backgroundTable {
            height: 100% !important;
            margin: 0;
            padding: 0;
            width: 100% !important;
        }

        body,
        #backgroundTable {
            background-color: #FFFFFF;
        }

        #container {
            border-radius: 8px;
            border: 1px solid #e5e5ea;
            margin-top: 16px;
            padding: 24px;
        }

        h1,
        .h1 {
            color: #1c1c1e;
            display: block;
            font-family: Arial, serif;
            font-size: 34px;
            font-weight: bold;
            line-height: 100%;
            margin-top: 0;
            margin-right: 0;
            margin-bottom: 8px;
            margin-left: 0;
            text-align: left;
        }

        h2,
        .h2 {
            color: #1c1c1e;
            display: block;
            font-family: Arial, serif;
            font-size: 30px;
            font-weight: bold;
            line-height: 100%;
            margin-top: 0;
            margin-right: 0;
            margin-bottom: 8px;
            margin-left: 0;
            text-align: left;
        }

        h3,
        .h3 {
            color: #1c1c1e;
            display: block;
            font-family: Arial, serif;
            font-size: 26px;
            font-weight: bold;
            line-height: 100%;
            margin-top: 0;
            margin-right: 0;
            margin-bottom: 8px;
            margin-left: 0;
            text-align: left;
        }

        h4,
        .h4 {
            color: #1c1c1e;
            display: block;
            font-family: Arial, serif;
            font-size: 22px;
            font-weight: bold;
            line-height: 100%;
            margin-top: 0;
            margin-right: 0;
            margin-bottom: 8px;
            margin-left: 0;
            text-align: left;
        }

        p {
            margin-top: 0;
            margin-right: 0;
            margin-bottom: 8px;
            margin-left: 0;
        }

        .content {
            color: #636366;
            font-family: Arial, serif;
            font-size: 14px;
            line-height: 125%;
            text-align: left;
        }

        .content a:link,
        .content a:visited,
        .content a .yshortcuts {
            color: #1c1c1e;
            font-weight: normal;
            text-decoration: underline;
        }

        .content img {
            display: inline;
            height: auto;
        }

        .image {
            max-width: 552px;
            height: auto;
        }

        #imageHeader {
            margin-bottom: 16px;
        }

        #imageFooter {
            margin-top: 16px;
        }

        .copyright {
            color: #636366;
            font-family: Arial, serif;
            font-size: 12px;
            line-height: 100%;
            text-align: center;
            padding-top: 12px;
            padding-bottom: 16px;
        }

        .btn {
            border-radius: 8px;
            box-shadow: 0 8px 16px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19);
            color:#FFFFFF !important;
            padding: 12px 28px;
            width: 50%;
            display: block;
            text-align: center;
            text-decoration: none !important;
            margin-top: 30px;
        }

        .btn-primary {
            background-color: #1c9910;
        }

        .btn-secondary {
            background-color: #1e4fb9;
        }

        @media only screen and (max-width: 500px) {
        .btn {
        width: 100% !important;
        }
    </style>
</head>

<body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
<center>
    <table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="backgroundTable">
        <tr>
            <td align="center" valign="top">
                <table border="0" cellpadding="0" cellspacing="0" width="552" id="container">
                    <!-- // Begin Header \\ -->
                    <tr>
                        <td align="center" valign="top">
                            <table border="0" cellpadding="0" cellspacing="0" width="552">
                                <tr>
                                    <td>
                                        <img src="{{ $headerImage }}" class="image" id="imageHeader"/>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- // End Header \\ -->

                    <!-- // Begin Body \\ -->
                    <tr>
                        <td align="center" valign="top">
                            <table border="0" cellpadding="0" cellspacing="0" width="552">
                                <tr>
                                    <td valign="top" class="content">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td valign="top">
                                                    {!! $content !!}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if ($footerImage)
                       <tr>
                            <td align="center" valign="top">
                                <table border="0" cellpadding="0" cellspacing="0" width="552">
                                    <tr>
                                        <td>
                                            <img src="{{ $footerImage }}" class="image" id="imageFooter"/>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    @if(isset($signature))
                        <!-- // Begin Signature \\ -->
                        <tr>
                            <td align="center" valign="top">
                                <table border="0" cellpadding="0" cellspacing="0" width="552">
                                    <tr>
                                        <td valign="top" class="content">
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                <tr>
                                                    <td valign="top">
                                                        <p style="margin-top: 10px;">
                                                            {!! strip_tags(html_entity_decode($signature), '<p><br><b><strong><i><em><u><a>') !!}
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <!-- // End Signature \\ -->
                    @endif
                </table>

                <!-- // Begin Copyright \\ -->
                <table border="0" cellpadding="0" cellspacing="0" width="552">
                    <tr>
                        <td align="center" valign="top">
                            <table border="0" cellpadding="0" cellspacing="0" width="552">
                                <tr>
                                    <td valign="top" class="copyright">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td valign="top">
                                                    <em>Copyright &copy; {{ date('Y') }} Octiv, all rights reserved.</em>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <!-- // End Copyright \\ -->
            </td>
        </tr>
    </table>
</center>
</body>
</html>
