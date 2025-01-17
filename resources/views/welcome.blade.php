<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Laravel</title>

        <style>
            body {
                font-family: serif;
                font-size: 18px;
                background: black;
                line-height: 1rem;
                color: white
            }
            p {
                text-align: center;
            }
        </style>
    </head>
    <body class="antialiased">

        <p style="bottom: 0; position: fixed; text-align: right">{{config('app.url')}} env. {{config('app.env')}}</p>
    </body>
</html>
