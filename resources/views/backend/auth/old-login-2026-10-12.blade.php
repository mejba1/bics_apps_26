<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>BICS | Admin Portal</title>
  <!-- Favicons -->
  <link href="{{ asset('assets/backend') }}/img/favicon.png" rel="icon">
  <!-- Vendor CSS Files -->
  <link href="{{ asset('assets/backend') }}/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="{{ asset('assets/backend') }}/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
  <main>
    <div class="container">
      <section class="section d-flex flex-column align-items-center justify-content-center">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">
              <div class="d-flex justify-content-center">
                <a href="#" class="logo d-flex align-items-center w-auto">
                  <img src="{{ asset('assets/backend') }}/img/logo.png" alt="">
                  <!--<span class="d-none d-lg-block">Bice</span>--->
                </a>
              </div><!-- End Logo -->
              <div class="card mb-3">
                <div class="card-body">
                  <div class="pt-4 pb-2">
                    <h5 class="card-title text-center pb-0 fs-4">BICS Admin Portal</h5>
                   <!-- <p class="text-center small">Enter your username & password to login</p>-->
                  </div>
                  @if(Session::has('error'))
                  <div class="alert alert-danger" role="alert">
                    {{ Session::get('error') }}
                  </div>
                @endif
                @if(Session::has('success'))
                <div class="alert alert-success" role="alert">
                  {{ Session::get('success') }}
                </div>
              @endif
                  <form class="row g-3" method="POST" action="{{ route('login.custom') }}">
                    @csrf

                    <!--<div class="col-12">
                      <label for="yourUsername" class="form-label">Username</label>
                      <div class="input-group has-validation">
                        <span class="input-group-text" id="inputGroupPrepend">@</span>
                        <input type="text" name="username" class="form-control" id="yourUsername" required>
                        <div class="invalid-feedback">Please enter your username.</div>
                      </div>
                    </div>-->

                    <div class="col-12">
                      <label for="email" class="form-label">Email</label>
                      <input type="email" name="email" class="form-control" placeholder="Enter registered email" id="email" required>
                      <div class="invalid-feedback">Please enter your Email!</div>
                    </div>
					 <div class="col-12">
                      <label for="yourPassword" class="form-label">Password</label>
                      <input type="password" name="password" placeholder="Enter correct password" class="form-control" id="password" required>
                      <div class="invalid-feedback">Please enter your password!</div>
                    </div>

                    <div class="col-12">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" value="true" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                      </div>
                    </div>
                    <div class="col-12">
                      <button class="btn btn-primary w-100" type="submit">Login</button>
                    </div>
                  </form>
                </div>
              </div>
              <div class="credits">
                Developed by @ BML IT Team
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main><!-- End #main -->
 <!-- Vendor JS Files -->
  <script src="{{ asset('assets/backend') }}/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="{{ asset('assets/backend') }}/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>