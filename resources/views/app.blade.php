<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <style>
            body {
                margin: 0;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background: #f3f4f6;
                color: #111827;
            }
            .guest-layout {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding-top: 1.5rem;
                background: #f3f4f6;
            }
            .guest-card {
                width: 100%;
                max-width: 28rem;
                margin-top: 1.5rem;
                padding: 1.5rem;
                background: #ffffff;
                box-shadow: 0 10px 15px rgba(0, 0, 0, 0.08);
                border-radius: 1rem;
            }
            .guest-card form {
                display: grid;
                gap: 1rem;
            }
            .guest-card label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 500;
            }
            .guest-card input[type="email"],
            .guest-card input[type="password"] {
                width: 100%;
                padding: 0.75rem 0.85rem;
                border: 1px solid #d1d5db;
                border-radius: 0.5rem;
                font-size: 1rem;
                box-sizing: border-box;
            }
            .guest-card .flex {
                display: flex;
                align-items: center;
            }
            .guest-card .block {
                display: block;
            }
            .guest-card .mt-4 {
                margin-top: 1rem;
            }
            .guest-card .mt-2 {
                margin-top: 0.5rem;
            }
            .guest-card .mb-4 {
                margin-bottom: 1rem;
            }
            .guest-card .ms-2 {
                margin-inline-start: 0.5rem;
            }
            .guest-card .ms-4 {
                margin-inline-start: 1rem;
            }
            .guest-card .underline {
                text-decoration: underline;
            }
            .guest-card a {
                color: #4b5563;
            }
            .guest-card a:hover {
                color: #111827;
            }
            .guest-card button {
                background: #4f46e5;
                border: none;
                color: #ffffff;
                padding: 0.75rem 1.25rem;
                border-radius: 0.5rem;
                cursor: pointer;
                font-weight: 600;
            }
            .guest-card button:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            .guest-card .font-medium {
                font-weight: 500;
            }
            .guest-card .text-sm {
                font-size: 0.875rem;
            }
            .guest-card .text-gray-600 {
                color: #4b5563;
            }
            .guest-card .bg-gray-100 {
                background: #f3f4f6;
            }
            .guest-card .bg-white {
                background: #ffffff;
            }
            .guest-card .shadow-md {
                box-shadow: 0 10px 15px rgba(0, 0, 0, 0.08);
            }
            .guest-card .rounded-md {
                border-radius: 0.5rem;
            }
            .guest-card .focus\:ring-2:focus,
            .guest-card .focus\:ring-offset-2:focus {
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
            }
            .guest-card .border-gray-300 {
                border-color: #d1d5db;
            }
        </style>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
