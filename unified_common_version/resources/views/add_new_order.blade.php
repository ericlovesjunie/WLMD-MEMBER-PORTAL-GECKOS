<!DOCTYPE html>
<html lang="en">

<head>

    <style>
        @font-face {
            font-family: "Gibson";
            font-weight: 100;
            src: url(./utils/fonts/gibson/fonnts.com-Gibson_Thin.otf) format("opentype");
        }

        @font-face {
            font-family: "Gibson";
            font-weight: 300;
            src: url(./utils/fonts/gibson/fonnts.com-Gibson_Light.otf) format("opentype");
        }

        @font-face {
            font-family: "Gibson";
            font-weight: 400;
            src: url(./utils/fonts/gibson/fonnts.com-Gibson_Regular.otf) format("opentype");
        }

        @font-face {
            font-family: "Gibson";
            font-weight: 500;
            src: url(./utils/fonts/gibson/fonnts.com-Gibson_Medium.otf) format("opentype");
        }

        @font-face {
            font-family: "Gibson";
            font-weight: 600;
            src: url(./utils/fonts/gibson/fonnts.com-Gibson_SemiBold.otf) format("opentype");
        }

        @font-face {
            font-family: "Gibson";
            font-weight: 700;
            src: url(./utils/fonts/gibson/fonnts.com-Gibson_Bold.otf) format("opentype");
        }

        @font-face {
            font-family: "Gibson";
            font-weight: 900;
            src: url(./utils/fonts/gibson/fonnts.com-Gibson_Heavy.otf) format("opentype");
        }

        @import url("https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap");

        :root {
            /* dark shades of primary color*/
            /* --font-base: "Poppins", sans-serif; */

            --font-base: "Inter", sans-serif;

            --clr-yellow: #ffd800;

            --clr-white: #fff;
            --clr-gray: #555;
            --clr-red-dark: hsl(360, 67%, 44%);
            --clr-red-light: hsl(360, 71%, 66%);
            --clr-green-dark: hsl(125, 67%, 44%);
            --clr-green-light: hsl(125, 71%, 66%);
            --clr-black: #000;
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
            max-width: 100vw;
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
            font-weight: 600;
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

        .section {
            padding: 4rem 3rem;
        }

        @media (max-width: 768px) {
            .section {
                padding: 3rem 1rem;
            }
        }

        /* loder start */
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

        /* loder end*/

        header {
            position: absolute;
            top: 0;
            /* background-color: #000; */
            font-weight: 700;
            width: 100%;
            padding: 1rem 3rem;
        }

        .footer {
            background-color: #2b2d31;
            color: #fff;
            /* position: absolute; */
            bottom: 0;
            width: 100%;
            text-align: start;
            padding: 1rem 1rem;
            border-top: 1px solid #333;
            color: #b9b9b9;
        }

        .footer-titel {
            position: relative;
            left: 25px;
            font-size: 16px;
            color: #555;
            font-family: "Gibson_Light";
        }

        .titel_img {
            width: 20px;
            height: 20px;
            position: relative;
            left: 5px;
        }
    </style>
    <style>
        /* src/PaymentGetways.css */

        .payment-header_p {
            padding: 1rem 0rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .paymen_p_tag {
            font-size: 25px;
        }

        .back_menu {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            cursor: pointer;
            color: #464643;
            font-weight: 500;
        }

        .item_img_card {
            width: 50px;
            height: 40px;
            object-fit: cover;
        }

        .payment-container {
            display: flex;
            flex-direction: column;
            margin: 0 auto;
            padding: 20px;
            max-width: var(--max-width);
            margin-bottom: 2rem;
        }

        .payment-content {
            max-width: var(--max-width);
            /* margin: auto; */
            gap: 10px;
        }

        .payment-content2 {
            display: flex;
            justify-content: space-between;
            max-width: var(--max-width);
            /* margin: auto; */
            gap: 10px;
        }

        .container {
            display: flex;
            justify-content: space-between;
            max-width: var(--max-width);
            /* margin: auto; */
            gap: 10px;
        }

        /* Form and Order Summary */
        .checkout-form {
            width: 70%;
            background: white;
            padding: 20px;
            border-radius: 5px;
            /* border-right: 0.1px solid #cacacabb; */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        /* Headings */
        .checkout-form h2,
        .checkout-form h3 {
            margin-bottom: 20px;
            color: #333;
            padding-top: 8px;
            display: flex;
            gap: 15px;
            align-items: center;
            font-size: 18px;
            font-weight: 700;
        }

        /* Form Group Styles */
        .form-group {
            margin-bottom: 15px;
            font-weight: 300;
        }

        .form-group-inline {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 15px;
        }

        .inline_seond {
            width: 50%;
        }

        /* Individual Form Groups within Inline Form Group */
        .form-group-inline .form-group {
            width: 50%;
        }

        input,
        select {
            width: 100%;
            padding: 10px 1rem 10px 10px;
            background-color: rgba(128, 128, 128, 0.09);
            border: 0.5px solid rgba(128, 128, 128, 0.09);
            border-radius: 5px;
            font-size: 16px;
            font-weight: 300;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            box-sizing: border-box;
        }

        select {
            background-image:url("{{ asset('public/assets/aniket_logo/down-arrow.png') }}");
            background-repeat: no-repeat;
            background-position: right 4px center;
            background-size: 12px;
        }

        .product_list {
            padding: 5px;
            margin-bottom: 10px;
            width: 100%;
            font-weight: 600;
        }

        .item-description {
            width: 100%;
        }

        /* Button Styles */
        .btn-checkout {
            background-color: #ffc107;
            color: #000;
            margin-top: 15px;
            padding: 10px 20px;
            border: 3px solid #ffc107;
            border-radius: 2rem;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            /* letter-spacing: 1px; */
            text-align: center;
        }

        .btn-checkout:hover {
            /* border: 1px solid var(--clr-yellow);/ */
            background-color: #fff;
            /* box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); */
        }

        /* Link Styles */
        .previous-step {
            display: inline-block;
            margin-top: 15px;
            text-decoration: none;
            color: #007f5f;
        }

        /* Order Summary Styles */

        .item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 15px;
            font-weight: 300;
            line-height: 20px;
            align-items: center;
            width: 100%;
        }

        .details_order p {
            font-size: 15px;
            font-weight: 500;
            line-height: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .img_order {
            width: 30%;
            padding: 10px;
            display: flex;
            justify-content: center;
            background-color: #005f4a;
            border-radius: 5px;
        }

        .item_img {
            object-fit: contain;
            max-width: 100px;
            height: 100px;
        }

        .img_order_del {
            width: 80%;
        }

        .img_order_del span {
            font-size: 13px;
            line-height: 25px;
            font-weight: 600;
            color: #484848;
        }

        .img_order_del p {
            font-size: 12px;
            font-weight: 500;
        }

        .img_order_del b {
            font-size: 12px;
            font-weight: 600;
        }

        .total_div {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .total_div b {
            font-weight: 700;
        }

        .summary-totals {
            border-top: 1px solid #ccc;
            padding-top: 10px;
            margin-top: 10px;
        }

        .summary-totals p {
            /* font-weight: bold; */
            color: #333;
        }

        .payment_card_titel b {
            display: flex;
            gap: 15px;
            align-items: center;
            font-weight: 700;
            color: #464643;
        }

        .error-message {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }

        input[type="checkbox"] {
            margin-right: 10px;
        }

        label {
            font-size: 14px;
            color: #333;
        }

        /* Payment Method Styles */
        #payment-method {
            cursor: pointer;
        }

        .check_img {
            width: 15px;
            height: 15px;
        }

        .hr {
            border: 1px solid #484848;
            margin-bottom: 15px;
            background: none;
            margin: 0 0 15px 0;
            height: 0 !important;
        }

        .terms_policy {
            color: #00c0ef;
            cursor: pointer;
        }

        .note_titel_ {
            width: 100%;
        }

        .note_titel_ p {
            font-size: 14px;
        }

        .payment_card {
            /* border: 1px solid #959292; */
            border-radius: 5px;
            /* box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); */
        }

        .payment_card_titel {
            /* border-bottom: 1px solid #333; */
            /* padding: 15px; */
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .card_icon_div {
            display: flex;
            gap: 10px;
        }

        .details-card {
            /* padding: 0.8rem 0.8rem; */
        }

        /* tabel */

        .order-summary {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            border-collapse: collapse;
            padding: 20px;
            border-radius: 5px;
            /* background-color: #fafafa; */
            /* box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);  */
        }

        .order-summary h3 {
            font-size: 22px;
        }

        .order-table {
            width: 100%;
            display: flex;
            margin-bottom: 15px;
        }

        .order-table th,
        .order-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .order-table th {
            font-weight: 600;
            text-align: center;
        }

        .item-details {
            display: flex;
            align-items: center;
        }

        .item-img {
            padding: 10px;
            display: flex;
            justify-content: center;
            background-color: #005f4a;
            border-radius: 5px;
            margin-right: 20px;
        }

        .item-img img {
            width: 100px;
            height: 100px;
            /* object-fit: cover; */
        }

        .item-description span {
            font-size: 14px;
            line-height: 25px;
            font-weight: 600;
            color: #484848;
        }

        .item-description p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #666;
        }

        /* Large Screens (Desktops) */
        @media screen and (min-width: 1024px) {
            .payment-content {
                max-width: var(--max-width);
            }

            /* .checkout-form,
            .order-summary {
                width: 50%;
                /* Form takes 70% 
            } */

            .order-summary {
                width: 50%;
                /* Summary takes 30% */
            }
        }

        /* Medium Screens (Tablets) */
        @media screen and (min-width: 768px) and (max-width: 1023px) {
            .payment-content {
                flex-direction: column;
                max-width: 750px;
            }

            .inline_seond {
                width: 100%;
            }

            .checkout-form,
            .order-summary {
                width: 100%;
                margin-bottom: 20px;
            }

            .form-group-inline {
                flex-direction: row;
            }

            .btn-checkout {
                font-size: 16px;
                padding: 12px;
            }

            .order-table {
                width: 100%;
            }
        }

        /* Small Screens (Mobile Devices) */
        @media screen and (max-width: 767px) {
            .order-table {
                width: 100%;
            }

            .inline_seond {
                width: 100%;
            }

            .payment-content {
                flex-direction: column;
                width: 100%;
            }

            .payment-content2 {
                flex-direction: column;
                width: 100%;
            }

            .payment_card_titel b {
                font-size: 14px;
                gap: 5px;
            }

            .checkout-form,
            .order-summary {
                width: 100%;
                margin-bottom: 20px;
                padding: 15px;
            }

            .checkout-form h2,
            .checkout-form h3 {
                font-size: 18px;
            }

            .form-group-inline {
                flex-direction: column;
            }

            .form-group-inline .form-group {
                width: 100%;
                margin-bottom: 10px;
            }

            .form-group input,
            .form-group select {
                font-size: 16px;
                padding: 10px;
            }

            .btn-checkout {
                font-size: 16px;
                padding: 12px;
            }

            .item_img {
                width: 70px;
                height: 60px;
            }

            .summary-totals p {
                font-size: 14px;
            }
        }

        #stickyio_cc_number {
            color: blue;
        }

        #stickyio_cc_expiry {
            color: green;
        }

        #stickyio_cc_cvv {
            color: red;
        }
    </style>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title id="site_name">Mensrx</title>
    <link rel="stylesheet" href="./paymentgetway.css" />
    <link rel="stylesheet" href="./index.css" />
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        /* Add your custom CSS rules here */
        #stickyio_cc_number {
            color: blue;
        }

        #stickyio_cc_expiry {
            color: green;
        }

        #stickyio_cc_cvv {
            color: red;
        }

        /* Error message styling */
        .error-message {
            /* position: fixed; */
            top: 20px;
            right: 20px;
            background-color: rgba(255, 255, 255, 0.9);
            color: red;
            /* border: 1px solid red; */
            padding: 5px;
            border-radius: 5px;
            z-index: 1000;
        }

        #stickyio_submit {
            opacity: 1 !important;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            color: white;
            text-transform: uppercase;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-content {
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            color: white;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        .loading-text {
            color: white;
            font-size: 18px;
            font-weight: 500;
            font-family: Arial, sans-serif;
        }

        header {
            position: absolute;
            top: 0;
            /* background-color: #000; */
            font-weight: 700;
            width: 100%;
            padding: 1rem 3rem;
        }

        .footer {
            background-color: #2b2d31;
            color: #fff;
            /* position: absolute; */
            bottom: 0;
            width: 100%;
            text-align: start;
            padding: 1rem 1rem;
            border-top: 1px solid #333;
            color: #b9b9b9;
        }

        .footer-titel {
            position: relative;
            left: 25px;
            font-size: 16px;
            color: #555;
            font-family: "Gibson_Light";
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @media screen and (max-width: 720px) {
            header {
                padding: 0.5rem 1.5rem;
            }

            .footer {
                padding: 0.5rem 1.5rem;
                font-size: 0.9rem;
            }

            .footer-titel {
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <!-- <div class="loading-overlay" id="loadingOverlay">
      <div class="loading-content">
        <div class="spinner"></div>
        <div class="loading-text">Processing Payment...</div>
      </div>
    </div> -->

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <div class="loading-text">loading...</div>
        </div>
    </div>

    <div style="position: relative">
        <header>
            <center>
                <img style="
              max-height: 60px;
              margin: auto;
              max-width: 160px;
              object-fit: contain;
            " src="" alt="" id="site_logo" />
            </center>
        </header>
        <div class="payment-container" style="position: relative">
            <div class="payment-content">
                <div class="payment-header_p">
                    <p class="paymen_p_tag">Checkout</p>
                </div>
                <div class="payment-content2">
                    <div class="checkout-form">
                        <h3>
                            <img src="{{ URL::asset('public/assets/aniket_logo/contact.png') }}" class="titel_img"
                                alt="" />Contact
                            Information
                        </h3>
                        <div class="form-group">
                            <input type="email" id="email" placeholder="Email Address" value="" />
                            <p class="error-message" id="email-error" style="display: none; color: red"></p>
                        </div>

                        <h3>
                            <img src="{{ URL::asset('public/assets/aniket_logo/delivery-truck.png') }}"
                                class="titel_img" alt="" />Shipping Information
                        </h3>
                        <div class="form-group-inline">
                            <div class="form-group">
                                <input type="text" id="first-name" placeholder="First Name" />
                                <p class="error-message" id="first-name-error" style="display: none; color: red"></p>
                            </div>
                            <div class="form-group">
                                <input type="text" id="last-name" placeholder="Last Name" />
                                <p class="error-message" id="last-name-error" style="display: none; color: red"></p>
                            </div>
                        </div>
                        <div class="form-group">
                            <input type="text" id="address" placeholder="Address" />
                            <p class="error-message" id="address-error" style="display: none; color: red"></p>
                        </div>
                        <div class="form-group-inline">
                            <div class="inline_seond">
                                <select id="countrySelect" onchange="fetchStates()">
                                    <option>United States</option>
                                </select>
                                <p class="error-message" id="country-error" style="display: none; color: red"></p>
                            </div>
                            <div class="inline_seond">
                                <select id="stateSelect" onchange="handleStateChange(this.value)">
                                    <option value="">Select state</option>
                                </select>
                                <p class="error-message" id="state-error" style="display: none; color: red"></p>
                            </div>
                        </div>
                        <div class="form-group-inline">
                            <div class="inline_seond">
                                <select id="citySelect">
                                    <option value="">Select city</option>
                                </select>
                                <p class="error-message" id="city-error" style="display: none; color: red"></p>
                            </div>
                            <div class="inline_seond">
                                <input type="text" id="postal-code" placeholder="Postal Code" />
                                <p class="error-message" id="postal-error" style="display: none; color: red"></p>
                            </div>
                        </div>
                        <div class="form-group">
                            <input type="text" id="phone" placeholder="Phone Number" maxlength="10" inputmode="tel" />
                            <p class="error-message" id="phone-error" style="display: none; color: red"></p>
                        </div>

                        <div class="payment_card">
                            <div class="payment_card_titel">
                                <b><img src="{{ URL::asset('public/assets/aniket_logo/credit-card.png') }}"
                                        class="titel_img" alt="" />Payment Methods</b>
                                <div class="card_icon_div">
                                    <img src="{{ URL::asset('public/assets/aniket_logo/visa.svg') }}"
                                        class="item_img_card" alt="Visa" />
                                    <img src="{{ URL::asset('public/assets/aniket_logo/master.svg') }}"
                                        class="item_img_card" alt="MasterCard" />
                                    <img src="{{ URL::asset('public/assets/aniket_logo/jcb.svg') }}"
                                        class="item_img_card" alt="JCB" />
                                    <img src="{{ URL::asset('public/assets/aniket_logo/american.svg') }}"
                                        class="item_img_card" alt="American Express" />
                                </div>
                            </div>
                            <div class="details-card">
                                <!-- <div class="form-group">
                  <select id="card-type">
                    <option value="1">Visa</option>
                    <option value="2">MasterCard</option>
                    <option value="3">Discover</option>
                    <option value="4">American Express</option>
                  </select>
                </div>
                <div class="form-group">
                  <input
                    type="text"
                    id="card-holder"
                    placeholder="Card holder name"
                  />
                  <p
                    class="error-message"
                    id="card-holder-error"
                    style="display: none; color: red"
                  ></p>
                </div>
                <div class="form-group">
                  <input
                    type="text"
                    id="card-number"
                    placeholder="Card Number"
                  />
                  <p
                    class="error-message"
                    id="card-number-error"
                    style="display: none; color: red"
                  ></p>
                </div> -->
                                <!-- <div class="form-group-inline">
                  <div class="form-group">
                    <input type="text" id="expiry-date" placeholder="MM/YY" />
                    <p
                      class="error-message"
                      id="expiry-error"
                      style="display: none; color: red"
                    ></p>
                  </div>
                  <div class="form-group">
                    <input type="text" id="cvv" placeholder="CVV" />
                    <p
                      class="error-message"
                      id="cvv-error"
                      style="display: none; color: red"
                    ></p>
                  </div>
                </div> -->

                                <div id="stickyio_order_form">
                                    <!-- Hidden input field to store the payment token -->
                                    <input name="payment_token" id="stickyio_payment_token" value=""
                                        style="display: none" />

                                    <!-- Include the payment form container -->
                                    <div id="stickyio_card" style="width: 100%"></div>

                                    <!-- Include the payment form submission button -->
                                    <button class="stickyio-btn" id="stickyio_submit" onclick="paymentSubmit()"
                                        style="margin: auto">
                                        Submit Payment
                                    </button>
                                </div>
                                <!-- <center>
                  <button
                    class="btn-checkout"
                    id="stickyio_submit"
                    onclick="paymentSubmit()"
                  >
                    Complete Checkout
                  </button>
                </center> -->
                            </div>
                        </div>
                    </div>

                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        <br />
                        <div class="order-table">
                            <div class="item-img">
                                <img id="product-img" src="product_image.png" class="item_img" alt="Product" />
                            </div>
                            <div class="item-description">
                                <select id="product-name" class="product_list">
                                    <option selected value="">Product Name</option>
                                </select>

                                <br />
                                <b id="product-price-description">00.00</b>
                            </div>
                        </div>
                        <hr class="hr" />
                        <div class="total_div">
                            <b>Grand Total</b>
                            <b id="grand-total-price">00.00</b>
                        </div>
                        <div class="details_order">
                            <p>
                                <img src="{{ URL::asset('public/assets/aniket_logo/check.png') }}" class="check_img"
                                    alt="" /> Provider &
                                Medication Included
                            </p>
                            <p>
                                <img src="{{ URL::asset('public/assets/aniket_logo/check.png') }}" class="check_img"
                                    alt="" /> On Demand
                                Virtual Doctor Visits
                            </p>
                            <p>
                                <img src="{{ URL::asset('public/assets/aniket_logo/check.png') }}" class="check_img"
                                    alt="" /> 30 Days
                                Weight Loss Guarantee
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer">
            <p class="footer-titel">
                © <b id="site_name"></b> 2024 All Rights Reserved
            </p>
        </div>
    </div>
    <!-- Sticky.io Integration Script -->
    <script>
        window.stickyAppKey = "whitelabelmd";

      const script = document.createElement("script");
      script.src = "https://cdn.sticky.io/jssdk/stickyio-sdk.js";
      document.head.appendChild(script);

      let isCardValid = false;

      script.onload = function () {
        const appId = "whitelabelmd";

        stickyio.creditCardForm(appId, function (card) {
          document.getElementById("stickyio_payment_token").value =
            card.payment_token;
          completeCheckout(card.payment_token);
        });
      };

      stickyio.onCardValidation = function (valid) {
        isCardValid = valid;
        updateSubmitButtonState();
      };

      stickyio.onTokenSuccess = function (token) {
        document.getElementById("stickyio_payment_token").value = token;
        document.getElementById("stickyio_order_form").submit();
      };

      stickyio.onTokenError = function (errors) {
        console.error("Tokenization error:", errors);
        alert(
          "Payment processing failed. Please check your card details and try again."
        );
      };

      function updateSubmitButtonState() {
        const formIsValid = validateForm();
        const submitButton = document.getElementById("stickyio_submit");

        submitButton.disabled = !(formIsValid && isCardValid);
      }

      function paymentSubmit(event) {
        event.preventDefault();
        const submitButton = document.getElementById("stickyio_submit");
        if (validateForm() && isCardValid) {
          submitButton.disabled = true;
          stickyio.tokenizeCard();
        }
      }

      function validateForm() {
        let isValid = true;
        const email = document.getElementById("email").value;
        if (!validateEmail(email)) {
          notifyError("email", "Please enter a valid Email Address.");
          isValid = false;
        } else {
          clearError("email");
        }
        const firstName = document.getElementById("first-name").value;
        if (!firstName) {
          notifyError("first-name", "Please enter First Name.");
          isValid = false;
        } else {
          clearError("first-name");
        }
        const lastName = document.getElementById("last-name").value;
        if (!lastName) {
          notifyError("last-name", "Please enter Last Name.");
          isValid = false;
        } else {
          clearError("last-name");
        }
        const address = document.getElementById("address").value;
        if (!address || address.length > 34) {
          notifyError(
            "address",
            "Please enter a valid Address (max 34 characters)."
          );
          isValid = false;
        } else {
          clearError("address");
        }
        const state = document.getElementById("stateSelect").value;
        if (!state) {
          notifyError("state", "Please select State.");
          isValid = false;
        } else {
          clearError("state");
        }
        const city = document.getElementById("citySelect").value;
        if (!city) {
          notifyError("city", "Please select City.");
          isValid = false;
        } else {
          clearError("city");
        }
        const postalCode = document.getElementById("postal-code").value;
        if (!postalCode) {
          notifyError("postal", "Please enter Postal Code.");
          isValid = false;
        } else {
          clearError("postal");
        }
        const phoneNumber = document.getElementById("phone").value;
        if (phoneNumber.length < 10) {
          notifyError("phone", "Please enter valid Phone Number (10 digits).");
          isValid = false;
        } else {
          clearError("phone");
        }
        return isValid; // Return form validation status
      }
      function validateEmail(email) {
        const re = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        return re.test(String(email).toLowerCase());
      }
      function notifyError(inputId, message) {
        const errorMessage = document.getElementById(inputId + "-error");
        errorMessage.textContent = message;
        errorMessage.style.display = "block";
      }
      function clearError(inputId) {
        const errorMessage = document.getElementById(inputId + "-error");
        errorMessage.style.display = "none";
      }
    </script>
    <!-- payment getway function end -->

    <script>
        let allCities = [];
      let selectedState = { name: "", state_code: "" };

      // Fetch states when page loads
      window.onload = async function fetchStates() {
        try {
          const response = await fetch(
            "https://countriesnow.space/api/v0.1/countries/states",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ country: "United States" }),
            }
          );
          const data = await response.json();
          const states = data.data.states;
          populateStates(states);
        } catch (error) {
          console.error("Error fetching states:", error);
        }
      };

      // Populate the state dropdown
      function populateStates(states) {
        const stateSelect = document.getElementById("stateSelect");
        states.forEach((state) => {
          const option = document.createElement("option");
          option.value = JSON.stringify({
            name: state.name,
            state_code: state.state_code,
          });
          option.textContent = state.name;
          stateSelect.appendChild(option);
          handleStateChange(option.textContent);
        });
      }

      // Handle state change
      async function handleStateChange(value) {
        const selectedStateObj = JSON.parse(value);
        selectedState = selectedStateObj;

        // Clear the city dropdown first
        const citySelect = document.getElementById("citySelect");
        citySelect.innerHTML = '<option value="">Select city</option>'; // Clear previous options

        try {
          const response = await fetch(
            "https://countriesnow.space/api/v0.1/countries/state/cities",
            {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                country: "United States",
                state: selectedState.name,
              }),
            }
          );
          const data = await response.json();

          // Check if the response contains the cities data
          if (data.data && data.data.length > 0) {
            allCities = data.data;
            console.log("Cities fetched:", allCities); // Debug: check fetched cities
            populateCities(allCities); // Populate the cities dropdown
          } else {
            console.error("No cities found for this state.");
          }
        } catch (error) {
          console.error("Error fetching cities:", error);
        }
      }

      // Populate city dropdown
      function populateCities(cities) {
        const citySelect = document.getElementById("citySelect");
        cities.forEach((city) => {
          const option = document.createElement("option");
          option.value = city;
          option.textContent = city;
          citySelect.appendChild(option);
        });
      }
    </script>

    <!-- <script>
      function getQueryParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
      }

      // Use the function to get the 'id' parameter
      const product_id = getQueryParam("id");
      console.log("product_id", product_id);

      let unique_id = "";
      let email_id = "";

      // This function runs when the DOM is fully loaded
      document.addEventListener("DOMContentLoaded", function () {
        if (!product_id) {
          console.error("No product_id found.");
          // window.location.href =
          //   "https://gokulnair.com/jalpesh/menrx_checkout/ErrorPage.html"; // Redirect on failure
          return; // Exit if product_id is not valid
        }

        console.log("Product ID:", product_id);

        // Add a delay of 3 seconds before making the API call
        setTimeout(async function fetchProductDetails() {
          try {
            // API call to fetch unique product data
            const response = await fetch(
              "https://gokulnair.com/levelup_backend/api/get_unique_id_data",
              {
                method: "POST",
                headers: {
                  "Content-Type": "application/json",
                },
                body: JSON.stringify({
                  unique_id: product_id, // Sending product_id in request body
                }),
              }
            );

            const data = await response.json();
            console.log("Response from get_unique_id_data:", data);

            // Check if the response status is successful
            if (data.status == 1 && data.product_id) {
              document.getElementById("loadingOverlay").style.display = "none";

              // Call fetchProduct with the product_id received from the response
              fetchProduct(data.product_id);
              unique_id = data.product_id;
              email_id = data.email;
              if (email_id) {
                document.getElementById("email").value = email_id;
              }
              // uniqProduct();
            } else {
              document.getElementById("loadingOverlay").style.display = "none";
              window.location.href =
                "https://gokulnair.com/jalpesh/menrx_checkout/ErrorPage.html"; // Redirect on failure
              console.error("No product data found or status not successful.");
            }
          } catch (error) {
            console.error("Error fetching product details:", error);
            document.getElementById("loadingOverlay").style.display = "none";
          }
        }, 3000); // 3-second delay before API call
      });

      // This function fetches detailed product information
      const uniqProduct = async () => {
        // Show loader while fetching data
        // document.getElementById("loadingOverlay").style.display = "block";

        try {
          // API call to fetch unique product data
          const response = await fetch(
            "https://gokulnair.com/levelup_backend/api/get_unique_id_data",
            {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
              },
              body: JSON.stringify({
                unique_id: product_id, // Sending product_id in request body
              }),
            }
          );

          const data = await response.json();
          console.log("Response from get_unique_id_data:", data);

          // Check if the response status is successful
          if (data.status == 1 && data.product_id) {
            document.getElementById("loadingOverlay").style.display = "none";

            // Call fetchProduct with the product_id received from the response
            fetchProduct(data.product_id);
            unique_id = data.product_id;
            email_id = data.email;
            if (email_id) {
              document.getElementById("email").value = email_id;
            }
          } else {
            document.getElementById("loadingOverlay").style.display = "none";
            window.location.href =
              "https://gokulnair.com/jalpesh/menrx_checkout/ErrorPage.html"; // Redirect on failure
            console.error("No product data found or status not successful.");
          }
        } catch (error) {
          console.error("Error fetching product details:", error);
          document.getElementById("loadingOverlay").style.display = "none";
        } finally {
          document.getElementById("loader").style.display = "none";
        }
      };

      // This function fetches detailed product information
      const fetchProduct = async (id) => {
        console.log("777", id);
        // Show loader while fetching data
        // document.getElementById("loadingOverlay").style.display = "block";

        try {
          // API call to fetch product details by product ID
          const response = await fetch(
            "https://gokulnair.com/levelup_backend/api/get_product_details",
            {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
              },
              body: JSON.stringify({
                product_id: id, // Pass the product ID received from previous API
              }),
            }
          );
          document.getElementById("loadingOverlay").style.display = "none";

          const data = await response.json();
          console.log("Response from get_product_details:", data);

          // Check if the product data exists and is valid
          if (data.status === 1 && data.product_data) {
            const product = data.product_data;

            // Set the product image if available
            const imgSrc = product.product_img[0]?.img_video;
            if (imgSrc) {
              document.getElementById("product-img").src = imgSrc;
            }

            // Set the product name in the dropdown
            document.getElementById("product-name").innerHTML = `
              <option selected value="${product.product_id}">${product.product_name}</option>
          `;

            // Set the product price
            const priceText = `$${product.product_price.toFixed(2)}`;
            document.getElementById("product-price-description").innerText =
              priceText; // Set price for description
            document.getElementById("grand-total-price").innerText = priceText; // Set price for grand total
          } else {
            console.error("No product data found or status not successful.");
          }
        } catch (error) {
          document.getElementById("loadingOverlay").style.display = "none";
          console.error("Error fetching product details:", error);
        } finally {
          // Hide the loader after data is fetched
          document.getElementById("loader").style.display = "none";
        }
      };
    </script> -->
    {{-- <p>Product ID: {{ $product_id }}</p> --}}
    <!-- working code -->
    <script>
        function getQueryParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
      }

      let site_name = "";

      // Use the function to get the 'id' parameter
      let product_id = @json($product_id);
      console.log("Product ID:", product_id);

      let unique_id = "";
      let email_id = "";

      // This function runs when the DOM is fully loaded
      document.addEventListener("DOMContentLoaded", function () {
        // Set a 2-second delay for reloading the page
        setTimeout(function () {
          // if (!sessionStorage.getItem("hasReloaded")) {
          //   // Perform the page reload
          //   sessionStorage.setItem("hasReloaded", "true");
          //   window.location.reload();
          // } else {
          // If the page has already been reloaded, fetch the product details
          fetchProductDetails();
          // }
        }, 2000);
      });

      // Function to fetch product details
      async function fetchProductDetails() {
        if (!product_id) {
          console.error("No product_id found.");
          window.location.href =
            "https://lightgoldenrodyellow-okapi-586794.hostingersite.com/eric_gettrim/error_page"; // Redirect on failure
          return; // Exit if product_id is not valid
        }

        console.log("Product ID:", product_id);

        try {
          // API call to fetch product details by product ID
          const response = await fetch(
            "https://lightgoldenrodyellow-okapi-586794.hostingersite.com/eric_gettrim/api/get_product_details",
            {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
              },
              body: JSON.stringify({
                product_id: product_id, // Pass the product ID received from previous API
              }),
            }
          );
          document.getElementById("loadingOverlay").style.display = "none";

          const data = await response.json();
          console.log("Response from get_product_details: 1", data);

          // Check if the product data exists and is valid
          if (data.status == 1 && data.product_data) {
            const product = data.product_data;

            // Set the product image if available
            const imgSrc = product.product_img[0]?.img_video;
            const site_logo = product.product[0]?.site_logo;
            site_name = product.product[0]?.site_name;
            console.log("product", product.product[0]?.site_logo);
            if (imgSrc) {
              document.getElementById("product-img").src = imgSrc;
              // document.getElementById("site_name").src = imgSrc;
            }
            if (site_logo) {
              document.getElementById("site_logo").src = site_logo;
            }
            if (site_name) {
              document.getElementById("site_name").innerHTML = site_name;
            }

            // Set the product name in the dropdown
            document.getElementById("product-name").innerHTML = `
                  <option selected value="${product.product_id}">${product.product_name}</option>
              `;

            // Set the product price
            const priceText = `$${product.product_price.toFixed(2)}`;
            document.getElementById("product-price-description").innerText =
              priceText; // Set price for description
            document.getElementById("grand-total-price").innerText = priceText; // Set price for grand total
          } else if (data.status == 0) {
            alert(data.message);
            window.location.href =
              "https://lightgoldenrodyellow-okapi-586794.hostingersite.com/eric_gettrim/error_page";
          } else {
            console.error("No product data found or status not successful.");
          }
        } catch (error) {
          document.getElementById("loadingOverlay").style.display = "none";
          console.error("Error fetching product details:", error);
        }
      }

      async function completeCheckout(token) {
        console.log("token", token);
        console.log("unique_id 11", product_id);

        const formIsValid = await validateForm();
        if (!formIsValid) return; // Don't proceed if validation failed

        // Now get the token and proceed with checkout
        // const paymentToken = document.getElementById(
        //   "stickyio_payment_token"
        // ).value;
        // document.getElementById("stickyio_payment_token").value = paymentToken;
        // const paymentToken = function (card) {
        //   document.getElementById("stickyio_payment_token").value =
        //     card.payment_token;
        // };
        // if (!paymentToken) {
        //   notifyError(
        //     "Payment token could not be generated. Please check your card details."
        //   );
        // } else {
        //   return;
        // }

        const email = document.getElementById("email").value;
        const firstName = document.getElementById("first-name").value;
        const lastName = document.getElementById("last-name").value;
        const address = document.getElementById("address").value;
        const state = JSON.parse(document.getElementById("stateSelect").value);
        const city = document.getElementById("citySelect").value;
        const postalCode = document.getElementById("postal-code").value;
        const phoneNumber = document.getElementById("phone").value;
        // const cardholdername = document.getElementById("card-holder").value;
        // const cardNumber = document.getElementById("card-number").value;
        // const expiryDate = document.getElementById("expiry-date").value;
        // const [month, year] = expiryDate.split("/");
        // const cvv = document.getElementById("cvv").value;
        console.log("state", state.state_code);

        const state_code = state.state_code;
        // Call checkout API after tokenization
        checkout_api(token, {
          email,
          // cardholdername,
          postalCode,
          state_code,
          firstName,
          lastName,
          address,
          city,
          phoneNumber,
          // cardNumber,
          // cvv,
        });
      }

      const checkout_api = async (token, param) => {
        try {
          document.getElementById("loadingOverlay").style.display = "flex";
          const response = await axios.post(
            "https://lightgoldenrodyellow-okapi-586794.hostingersite.com/eric_gettrim/api/add_user_check_product",
            {
              email: param.email,
              // card_type: 1,
              // card_no: param.cardNumber,
              // ex_month: month,
              // ex_year: year,
              // cvv_no: param.cvv,
              // card_holder_name: param.cardholdername,
              zip_code: param.postalCode,
              state_name: param.state_code,
              first_name: param.firstName,
              last_name: param.lastName,
              product_id: product_id,
              address: param.address,
              city_name: param.city,
              phone: param.phoneNumber,
              payment_token: token,
            }
          );
          await new Promise((resolve) => setTimeout(resolve, 4000));

          document.getElementById("loadingOverlay").style.display = "none";
          if (response.data.status == 1) {
            window.location.href =
              "https://lightgoldenrodyellow-okapi-586794.hostingersite.com/eric_gettrim/add_order";
            console.log("res", response.data);
          } else if (response.data.status == 4 && response.data.user_id) {
            // window.location.href = `https://gokulnair.com/jalpesh/checkout_page_mensrx/index2.html?id=${product_id}&user_id=${error.response.data.user_id}`;
            window.location.href = `https://lightgoldenrodyellow-okapi-586794.hostingersite.com/eric_gettrim/card_declined/${product_id}/${user_id}`;
            alert("Your card is declined, please try with another card");
          } else {
            console.log("111", 111);
            alert(response.data.message);
            location.reload();
            // notifyError("An error occurred during checkout.");
          }
        } catch (error) {
          console.log("111", error.response.data.user_id);
          // if (error.response.data?.user_id && error.response.data.status == 4) {
          //   window.location.href = `https://gokulnair.com/jalpesh/checkout_page/index2.html?id=${product_id}&user_id=${error.response.data.user_id}`;
          // }
          alert("Somthing want wrong");

          location.reload();
          // await new Promise((resolve) => setTimeout(resolve, 3000));
          document.getElementById("loadingOverlay").style.display = "none";
          console.error("Error during checkout:", error);
          // notifyError("An error occurred. Please try again.");
        } finally {
          document.getElementById("loadingOverlay").style.display = "none";
        }
      };
    </script>

    <script>
        document.getElementById("phone").addEventListener("input", function (e) {
        this.value = this.value.replace(/[^0-9]/g, "");
        if (this.value.length > 10) {
          this.value = this.value.slice(0, 10);
        }
        const phoneNumber = this.value;
        if (phoneNumber.length < 10) {
          notifyError("phone", "Phone number must be exactly 10 digits.");
        } else {
          clearError("phone");
        }
      });
      function notifyError(fieldId, message) {
        const errorElement = document.getElementById(`${fieldId}-error`);
        errorElement.textContent = message;
        errorElement.style.display = "block";
      }
      function clearError(fieldId) {
        const errorElement = document.getElementById(`${fieldId}-error`);
        errorElement.textContent = "";
        errorElement.style.display = "none";
      }
    </script>
</body>

</html>