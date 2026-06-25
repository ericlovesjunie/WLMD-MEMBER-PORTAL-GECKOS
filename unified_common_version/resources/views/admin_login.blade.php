<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="keywords" content="" />
    <meta name="author" content="" />
    <meta name="robots" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Workload : Workload Project Management Admin  Bootstrap 5 Template" />
    <meta property="og:title" content="Workload : Workload Project Management Admin  Bootstrap 5 Template" />
    <meta property="og:description" content="Workload : Workload Project Management Admin  Bootstrap 5 Template" />
    <meta property="og:image" content="page-error-404.html" />
    <meta name="format-detection" content="telephone=no">
    {{-- <---this three line add to show message --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    {{-- -->> --}}

    @php
    $theme = DB::table('theme_colores')->latest()->first();
    @endphp
    <!-- PAGE TITLE HERE -->
    {{-- <title>mddirect</title> --}}
    <title>{{ config('app.name') }}</title>

    <!-- FAVICONS ICON -->
    @if($theme)
    <link rel="icon" href="{{ URL::asset('public/assets/theme_favicon/' . $theme->theme_favicon) }}" type="image/png">
    @else

    @endif
    {{-- <link rel="shortcut icon" type="image/png" href="images/favicon.png" /> --}}
    <link href="{{ URL::asset('public/assets/css/style.css') }}" rel="stylesheet">
</head>

<body class="vh-100">
    <div class="authincation h-100">
        <div class="container h-100">
            <div class="row justify-content-center h-100 align-items-center">
                <div class="col-md-6">
                    <div class="authincation-content">
                        <div class="row no-gutters">
                            <div class="col-xl-12">
                                <div class="auth-form">
                                    <div class="text-center mb-3">
                                        {{-- <img src="{{ URL::asset('public/assets/logo/bg_logo.png') }}" alt="" width="28%" height="23%"> --}}
                                        @if($theme)
                                        <img src="{{ URL::asset('public/assets/theme_logo/' . $theme->theme_logo) }}" alt="" width="28%" height="23%">
                                        @else

                                        @endif
                                    </div>
                                    {{-- <h4 class="text-center mb-4">Sign in your account</h4> --}}
                                    <form class="needs-validation" action="{{ url('admin_login') }}" method="POST" data-parsley-validate> @csrf
                                        <div class="mb-3">
                                            <label class="mb-1"><strong>Email</strong></label>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="mb-1"><strong>Password</strong></label>
                                            <input type="password" name="password" class="form-control" value="" required>
                                        </div>

                                        <div class="text-center">
                                            {{-- <button type="submit" class="btn btn btn-block" style="background-color: #3cf115">Sign In</button> --}}
                                            <button type="submit" class="btn btn-block" style="background-color: {{ $theme->theme_colore ?? '#3cf115' }}; color: #fff;">Sign In</button>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!--**********************************
        Scripts
    ***********************************-->
    <!-- Required vendors -->
    <script src="{{ URL::asset('public/assets/vendor/global/global.min.js') }}"></script>

    <script src="{{ URL::asset('public/assets/js/custom.min.js') }}"></script>
    <script src="{{ URL::asset('public/assets/js/dlabnav-init.js') }}"></script>
    {{-- <script src="{{ URL::asset('public/assets/js/styleSwitcher.js') }}" ></script> --}}

    @include('include.message')

</body>
</html>
