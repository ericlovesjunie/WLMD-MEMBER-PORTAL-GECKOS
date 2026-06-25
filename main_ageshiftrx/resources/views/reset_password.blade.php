<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="./OTPScreen.css">
    <link rel="stylesheet" href="./index.css">
    <!-- jQuery -->
    @php
    $theme = DB::table('theme_colores')->latest()->first();
    $is_admin = session('IsAdmin');

    @endphp
    <!-- FAVICONS ICON -->
    {{-- <link rel="icon" href="{{ URL::asset('public/assets/logo/logofav.png') }}" type="image/png" /> --}}
    {{-- <link rel="icon" href="{{ asset($theme->theme_logo) }}" type="image/png"> --}}
    @if($theme)
    <link rel="icon" href="{{ URL::asset('public/assets/theme_favicon/' . $theme->theme_favicon) }}" type="image/png">
    @else
        
    @endif
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    {{-- <link rel="icon" href="{{ URL::asset('public/assets/logo/logofav.png') }}" type="image/png" /> --}}
    <!-- Toastr CSS and JS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <style>
        .login_img_wrapp {
            width: 100vw;
            height: 100vh;
            background-image: url("{{ asset('public/assets/aniket_logo/login_back_img.jpg') }}");
            background-size: cover;
            background-position: top;
            background-repeat: no-repeat;
            position: relative;

            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;

            max-width: 100vw;
            max-height: 100vh;
            overflow: hidden;
        }

        .lock_icon {
            width: 25px;
        }

        .login_img_wrapp::after {
            content: "";
            position: absolute;
            background: #111111cf;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
        }

        .login_inner_werapp {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 0.5rem;
        }

        .login_white_wrapp {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 2rem 10px;
            width: 24vw;
            min-width: 320px;
            min-height: 80vh;

            background: var(--clr-white);
            z-index: 11;
        }

        .login_black_logo {
            width: 180px;
            object-fit: contain;
        }

        .login_inputs_wrapp {
            width: 90%;
            margin: 1rem auto;

            display: flex;
            flex-direction: column;
        }

        .login_label {
            font-size: 14px;
            font-weight: 400;
            line-height: 24px;
            text-align: left;
            color: var(--clr-gray);
            margin-bottom: 0.5rem;
        }

        .login_single_input_wrapp {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f1f3f6;
            border-radius: 8px;
            /* margin-bottom: 1rem; */
        }

        .input_yellow_btn {
            width: 40px;
            height: 40px;
            background: var(--clr-mint);

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 8px;
            cursor: pointer;
        }

        .login_single_input_wrapp>input {
            width: 80%;
            margin-left: 8px;
            border: none;
            z-index: 2;
            background: transparent;
        }

        .login_forgaot_btn {
            font-size: 14px;
            font-weight: 400;
            line-height: 21px;
            text-align: right;
            /* width: 100%; */
            align-self: right;
            color: #1e2772;
            text-decoration: underline;

            cursor: pointer;
            margin: 8px 0;
        }


        @import url("https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap");

        @font-face {
            font-family: "ProximaNova";
            src: url("./utils/proxima-nova/Proxima\ Nova\ Black.otf") format("opentype");
            font-style: normal;
            font-weight: 900;
        }

        @font-face {
            font-family: "ProximaNova";
            src: url("./utils/proxima-nova/Proxima\ Nova\ Extrabold.otf") format("opentype");
            font-style: normal;
            font-weight: 700;
        }

        @font-face {
            font-family: "ProximaNova";
            src: url("./utils/proxima-nova/Proxima\ Nova\ Bold.otf") format("opentype");
            font-style: normal;
            font-weight: 600;
        }

        @font-face {
            font-family: "ProximaNova";
            src: url("./utils/proxima-nova/Proxima\ Nova\ Semibold.otf") format("opentype");
            font-style: normal;
            font-weight: 500;
        }

        @font-face {
            font-family: "ProximaNova";
            src: url("./utils/proxima-nova/ProximaNova-Regular.otf") format("opentype");
            font-style: normal;
            font-weight: 400;
        }

        @font-face {
            font-family: "font-400";
            src: url("./utils/proxima-nova/Proxima\ Nova\ Alt\ Light.otf") format("opentype");
            font-style: normal;
            font-weight: 400;
        }

        @font-face {
            font-family: "ProximaNova";
            src: url("./utils/proxima-nova/Proxima\ Nova\ Thin.otf") format("opentype");
            font-style: normal;
            font-weight: 300;
        }

        :root {
            /* dark shades of primary color*/
            /* --font-base: "Poppins", sans-serif; */
            --font-base: "ProximaNova", sans-serif;
            --clr-yellow: #ffd800;
            --clr-mint:{{ $theme->theme_colore ?? '#ffd800' }};

            --clr-white: #fff;
            --clr-gray: #555;
            --clr-red-dark: hsl(360, 67%, 44%);
            --clr-red-light: hsl(360, 71%, 66%);
            --clr-green-dark: hsl(125, 67%, 44%);
            --clr-green-light: hsl(125, 71%, 66%);
            --clr-black: #1a1a1a;
            --clr-black-blue: #292731;
            --transition: all 0.3s linear;
            --spacing: 0.1rem;
            --radius: 0.25rem;
            --light-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --dark-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            --max-width: 1240px;
            --fixed-width: 620px;
        }

        * {
            box-sizing: border-box;
            padding: 0;
            margin: 0;
            scroll-behavior: smooth;
            font-family: var(--font-base);
            /* font-family: "Lato", sans-serif !important; */
        }

        ::after,
        ::before {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-base);
            background: var(--color-bg);
            overflow-x: hidden;
        }

        figure {
            margin: 0px !important;
        }

        html,
        body,
        div,
        span,
        applet,
        object,
        iframe,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        p,
        blockquote,
        pre,
        a,
        abbr,
        acronym,
        address,
        big,
        cite,
        code,
        del,
        dfn,
        em,
        img,
        ins,
        kbd,
        q,
        s,
        samp,
        small,
        strike,
        strong,
        sub,
        sup,
        tt,
        var,
        b,
        u,
        i,
        center,
        dl,
        dt,
        dd,
        ol,
        ul,
        li,
        fieldset,
        form,
        label,
        legend,
        table,
        caption,
        tbody,
        tfoot,
        thead,
        tr,
        th,
        td,
        article,
        aside,
        canvas,
        details,
        embed,
        figure,
        figcaption,
        footer,
        header,
        hgroup,
        menu,
        nav,
        output,
        ruby,
        section,
        summary,
        time,
        mark,
        audio,
        video {
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 100%;
            font: inherit;
            vertical-align: baseline;
            font-family: var(--font-base);
        }

        article,
        aside,
        details,
        figcaption,
        figure,
        footer,
        header,
        hgroup,
        menu,
        nav,
        section {
            display: block;
        }

        ol,
        ul {
            list-style: none;
        }

        blockquote,
        q {
            quotes: none;
        }

        blockquote:before,
        blockquote:after,
        q:before,
        q:after {
            content: "";
            content: none;
        }

        table {
            border-collapse: collapse;
            border-spacing: 0;
        }

        a {
            color: unset;
            text-decoration: none;
        }

        .underline {
            text-decoration: underline !important;
        }

        .underline:hover {
            text-decoration: none !important;
        }

        strong,
        b {
            font-weight: bold;
        }

        i {
            font-style: italic;
        }

        hr {
            box-sizing: content-box;
            height: 0;
            overflow: visible;
        }

        .hr {
            width: 100%;
            height: 2px;
            background: var(--color-light-gray);
            margin: 1rem auto;
        }

        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -webkit-appearance: textfield;
            -moz-appearance: textfield;
            appearance: textfield;
        }

        input[type="checkbox"] {
            transform: scale(1.3);
        }

        button:focus,
        input:focus,
        select:focus,
        textarea:focus {
            outline: none !important;
        }

        button {
            border: none;
            background: none;
            cursor: pointer;
        }

        button[type="submit"] {
            cursor: pointer;
            border: none;
        }

        button:disabled {
            cursor: not-allowed;
            opacity: 80%;
        }

        textarea {
            resize: none;
        }

        a {
            -webkit-transition: all 0.2s;
            transition: all 0.2s;
        }

        a,
        a:hover,
        a:active,
        a:focus {
            text-decoration: none;
            outline: none;
        }

        /* section start */

        .section {
            padding: 5rem 0;
        }

        .section-center {
            width: 90vw;
            margin: 0 auto;
            max-width: var(--max-width);
        }

        @media screen and (min-width: 992px) {
            .section-center {
                width: 95vw;
            }
        }

        /* section end */

        /* colors */

        .f-white {
            color: var(--clr-white);
        }

        .f-black {
            color: var(--clr-black);
        }

        /* fonts */

        .f-12 {
            font-size: 12px;
            font-weight: 300;
            line-height: 125%;
        }

        .f-14 {
            font-size: 14px;
            font-weight: 400;
            line-height: 125%;
        }

        .f-16 {
            font-size: 16px;
            line-height: 125%;
        }

        .f-18 {
            font-size: 18px;
            line-height: 125%;
        }

        .f-20 {
            font-size: 20px;
            line-height: 125%;
        }

        .fw-900 {
            font-weight: 900;
        }

        .fw-700 {
            font-weight: 700;
        }

        .fw-500 {
            font-weight: 500;
        }

        /* margines start */

        .mb-1 {
            margin-bottom: 1rem;
        }

        .mb-1_5 {
            margin-bottom: 1.5rem;
        }

        .mb-2 {
            margin-bottom: 2rem;
        }

        .mb-3 {
            margin-bottom: 3rem;
        }

        .mb-05 {
            margin-bottom: 0.5rem;
        }

        .mt-2 {
            margin-top: 2rem;
        }

        .mx-2 {
            margin: 2rem;
        }

        .mx-1-5 {
            margin: 1.5rem;
        }

        .mx-1 {
            margin: 1rem;
        }

        /* margines end */

        /* buttons start */

        .btn {
            /* text-transform: uppercase; */
            background: var(--clr-mint);
            color: var(--clr-black);
            padding: 0.375rem 0.75rem;
            /* letter-spacing: var(--spacing); */
            display: inline-block;
            font-weight: 600;
            transition: var(--transition);
            font-size: 0.875rem;
            cursor: pointer;
            /* box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2); */
            border-radius: var(--radius);
            border-color: transparent;
            width: 100%;
            height: 50px;
            max-width: 400px;
        }

        .btn:hover {
            /* box-shadow: 0px 8px 12px 0px #fd74014d; */
            transition: 0.3s ease-in all;
            /* background: var(--clr-white); */
            /* color: var(--clr-black); */
            border: 1px solid var(--clr-black);
        }

        .btn-2 {
            /* text-transform: uppercase; */
            background: var(--clr-yellow);
            color: var(--clr-black);
            padding: 0.375rem 0.75rem;
            /* letter-spacing: var(--spacing); */
            display: inline-block;
            font-weight: 600;
            transition: var(--transition);
            font-size: 0.875rem;
            cursor: pointer;
            /* box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2); */
            border-radius: var(--radius);
            border-color: transparent;
            width: 100%;
            height: 50px;
            max-width: 400px;
        }

        .btn-2:hover {
            /* box-shadow: 0px 8px 12px 0px #fd74014d; */
            transition: 0.3s ease-in all;
            background: var(--clr-white);
            color: var(--clr-black);
            border: 1px solid var(--clr-black);
        }

        .btn-3 {
            /* text-transform: uppercase; */
            background: var(--clr-white);
            color: #18181bb8;
            padding: 0.375rem 0.75rem;
            /* letter-spacing: var(--spacing); */
            display: inline-block;
            font-weight: 600;
            transition: var(--transition);
            font-size: 0.875rem;
            cursor: pointer;
            /* box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2); */
            border-radius: var(--radius);
            border-color: transparent;
            width: 100%;
            height: 50px;
            max-width: 400px;
            border: 1px solid #718096;
        }

        .btn-3:hover {
            /* box-shadow: 0px 8px 12px 0px #fd74014d; */
            transition: 0.3s ease-in all;
            background: var(--clr-mint);
            color: var(--clr-black);
            border: 1px solid var(--clr-mint);
        }

        /* widths */

        .w-90 {
            width: 90% !important;
        }

        .w-80 {
            width: 80% !important;
        }

        .w-70 {
            width: 70% !important;
        }

        .w-60 {
            width: 60% !important;
        }

        /* scrollbar style start */

        .main-content::-webkit-scrollbar {
            width: 10px;
        }

        /* Track */
        .main-content::-webkit-scrollbar-track {
            box-shadow: inset 0 0 1px grey;
            border-radius: 6px;
        }

        /* Handle */
        .main-content::-webkit-scrollbar-thumb {
            background: #979797;
            border-radius: 10px;
        }

        /* Handle on hover */
        .main-content::-webkit-scrollbar-thumb:hover {
            background: #7c7c7c;
        }

        /* scrollbar style start */

        /* MainLayout.css */
        .main-layout {
            display: grid;
            grid-template-areas:
                "sidebar header"
                "sidebar main";
            grid-template-columns: 250px 1fr;
            grid-template-rows: 100px 1fr;
            height: 100vh;
            transition: grid-template-columns 0.3s ease;
        }

        .main-layout.sidebar-collapsed {
            grid-template-columns: 80px 1fr;
        }

        .main-layout.sidebar-hidden {
            grid-template-columns: 0 1fr;
        }

        .main-content {
            grid-area: main;
            padding: 0px;
            overflow-y: scroll;
        }

        .unselectable {
            user-select: none;
            -webkit-user-select: none;
            /* Safari */
            -moz-user-select: none;
            /* Firefox */
            -ms-user-select: none;
            /* Internet Explorer/Edge */
            -o-user-select: none;
            /* Opera */
        }

        @keyframes spinner {
            to {
                transform: rotate(360deg);
            }
        }

        .loading {
            width: 6rem;
            height: 6rem;
            margin: 0 auto;
            margin-top: 10rem;
            border-radius: 50%;
            border: 4px solid #ccc;
            border-top-color: var(--clr-primary-5);
            animation: spinner 0.6s linear infinite;
        }

        @media (max-width: 768px) {
            .main-layout {
                grid-template-areas:
                    "header"
                    "main";
                grid-template-columns: 1fr;
                grid-template-rows: 100px 1fr;
            }

            .main-layout.sidebar-collapsed {
                grid-template-columns: 1fr;
            }

            .main-layout.sidebar-hidden {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-layout {
                grid-template-areas:
                    "header"
                    "main";
                grid-template-columns: 1fr;
                grid-template-rows: 60px 1fr;
            }

            .main-layout.sidebar-collapsed {
                grid-template-columns: 1fr;
            }

            .main-layout.sidebar-hidden {
                grid-template-columns: 1fr;
            }
        }

        /* media quarys start */

        @media (max-width: 420px) {
            .btn {
                max-width: 300px;
            }
        }

        /* media quarys end */

    </style>
</head>



<body>
    @if(session('success'))
    <div class="alert alert-success" id="successMessage">
        {{ session('success') }}
    </div>
    <?php
        $site_data = DB::table('site')->first();
        $memberportal_url = $site_data->memberportal_url;
    ?>
    <script>
        
        // Set a 2-second delay before redirecting to the external URL
        setTimeout(function() {
            window.location.href = "https://member.priderx.com/";
        
        }, 2000); // 2000 milliseconds = 2 seconds

    </script>
    @endif
    <form action="{{ url('password_change') }}" method="POST">
        @csrf
        <div class="login_img_wrapp">
            <div class="section-center login_inner_werapp">
                <div class="login_white_wrapp">

                    @if($theme)
                    <img src="{{ URL::asset('public/assets/theme_logo/' . $theme->theme_logo) }}" alt="Logo" class="login_black_logo">
                    @else
                        
                    @endif
                    {{-- <img src="{{ URL::asset('public/assets/logo/bg_logo33333333.png') }}" style="background: black" alt="Logo" class="login_black_logo" /> --}}
                    <p class="mx-1-5 f-20 f-black fw-900">Reset Password</p>

                    <!-- Include the messages partial to display flash messages -->


                    <div class="login_inputs_wrapp">
                        <!-- New Password Field with Show/Hide Toggle -->
                        <label for="newPassword" class="login_label">New Password</label>
                        <div class="login_single_input_wrapp mb-1">
                            <input type="password" id="newPassword" name="password" placeholder="Enter new password" required />
                            <input type="hidden" value="{{ $data->user_id }}" name="user_id">
                            <button type="button" class="input_yellow_btn" onclick="togglePasswordVisibility('newPassword', 'newPasswordToggle')">
                                <img src="{{ URL::asset('public/assets/aniket_logo/view.png') }}" id="newPasswordToggle" class="lock_icon" alt="Show Password">
                            </button>
                        </div>

                        <!-- Confirm New Password Field with Show/Hide Toggle -->
                        <label for="confirmPassword" class="login_label">Confirm New Password</label>
                        <div class="login_single_input_wrapp mb-1">
                            <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm new password" required />
                            <button type="button" class="input_yellow_btn" onclick="togglePasswordVisibility('confirmPassword', 'confirmPasswordToggle')">
                                <img src="{{ URL::asset('public/assets/aniket_logo/view.png') }}" id="confirmPasswordToggle" class="lock_icon" alt="Show Password">
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn mt-1 w-90" style="color: #fff;">Submit</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        // Function to toggle password visibility
        function togglePasswordVisibility(passwordFieldId, toggleIconId) {
            const passwordField = document.getElementById(passwordFieldId);
            const toggleIcon = document.getElementById(toggleIconId);

            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.src = "{{ URL::asset('public/assets/aniket_logo/hide.png') }}"; // Update icon for hiding
            } else {
                passwordField.type = "password";
                toggleIcon.src = "{{ URL::asset('public/assets/aniket_logo/view.png') }}"; // Update icon for showing
            }
        }

    </script>

</body>
</html>


@include('include.message')
