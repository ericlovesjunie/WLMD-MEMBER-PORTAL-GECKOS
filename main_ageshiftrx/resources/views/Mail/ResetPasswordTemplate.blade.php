<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">

<!-- jQuery library -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.slim.min.js"></script>

<!-- Popper JS -->
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>

<!-- Latest compiled JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
    <title>Edupops | Reset Password</title>
</head>

<style>
    body {
  margin: 0;
  padding: 0;
  background: linear-gradient(90deg, rgba(107,27,228,1) 0%, rgba(144,0,240,1) 0%, rgba(176,93,181,1) 100%, rgba(107,27,228,1) 100%);
  height: 100%;
  width: 100%;
  background-size: cover;
}
#login .container #login-row #login-column #login-box {
  margin-top: 50px;
  max-width: 500px;
  height: 320px;
  border: 1px solid #EAEAEA;
  background-color: #EAEAEA;
  border-radius: 25px;
}
#login .container #login-row #login-column #login-box #register_form {
  padding: 20px;
}
#login .container #login-row #login-column #login-box #register_form #register-link {
  margin-top: -85px;
}
.btn-info {
    color: #fff;
    background-color: #9102ef;
    border-radius: 10px;
    padding: 10px 30px;
}
.text-info {
    color: #a846c3!important;
}
.btn-info:hover {
    color: #fff;
    background-color: #9102ef;
}
btn-info:focus {
    color: #fff;
    background-color:#9313EA !important;
}
</style>
<!------ Include the above in your HEAD tag ---------->

<body>
    <div id="login">
        <h3 class="text-center text-white pt-5">Reset Password</h3>
        <div class="container">
            <div id="login-row" class="row justify-content-center align-items-center">
                <div id="login-column" class="col-md-6 ">
                    <div id="login-box" class="col-md-12">
                        <form id="register_form" class="form" action="{{ url('setpassword') }}" method="POST"> @csrf

                            <input type="hidden" name="user_id" value="{{ $user_id }}">
                            <input type="hidden" name="user_email" value="{{ $email }}">

                            <div class="form-group">
                                <label for="username" class="text-info">New Password</label><br>
                                <input type="text" name="newpassword" id="#password-field" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="password" class="text-info">Confirm Password</label><br>
                                <input type="text" name="confirmpassword" id="password" class="form-control">
                            </div>
                            <div class="form-group text-center mt-5 ">
                                <input type="submit" name="submit" class="btn btn-info btn-md border-0" value="Submit">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="{{ URL::asset('public/assets/js/jquery.validate.js') }}"></script>

<script type="text/javascript">
  $(document).ready(function(){
      $("#register_form").validate({
           rules: {
            newpassword: {
                 required: true,
                 minlength: 8,
                 maxlength: 18,
              },
              confirmpassword: {
                 required: true,
                 equalTo: "#password-field",
                 minlength: 8,
                 maxlength: 18,
              },
           },
          messages: {
            newpassword: {
                 required: "Create a new Password..!",
              },
              confirmpassword: {
                 required: "Yor new Password must be match to your new Password..!",
              },
          },
          errorPlacement: function (error, element) {
              element.closest(".field_error").append(error);
          },
      });
  });
</script>