@extends('templates.welcome')

@section('title')
Sistem Informasi Manajemen Pegawai
@endsection

@section('content')
<nav class="bg-gray-200">
	<div class="container-fluid">
	  <div class="row">
		<div class="col-12">
		  <!-- Breadcrumb -->
		  <ol class="breadcrumb breadcrumb-scroll">
			<li class="breadcrumb-item">
			  <a href="/" class="text-gray-700">
				SIMPEG Dinas Pendidikan Kota Palu
			  </a>
			</li>
			<li class="breadcrumb-item active" aria-current="page">
			    Cek Berkas
			</li>
		  </ol>

		</div>
	  </div> <!-- / .row -->
	</div> <!-- / .container -->
</nav>
<div class="container">
    <h1 class="text-center fw-bold my-5">Cek Berkas</h1>

    <div class="row row-cols-lg-12">
        <div class="col-8">
            <input type="text" class="form-control border border-primary" id="idDokumen" placeholder="ID Dokumen">
        </div>

        <div class="col-1">
            <button type="button" class="btn btn-primary" id="btnCari">Cari</button>
        </div>
        <div class="col">
            <button class="btn btn-warning" id="btnQrcode" data-backdrop="static" data-toggle="modal">Scan
                Barcode</button>
        </div>
    </div>
</div>

<div class="container dokumen my-5 border p-5" id="surat">

</div>


<!-- Modal Barcode Voucher-->
<!-- Modal -->
<div class="modal fade" id="modalQrcode" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-4">
                <h5 class="modal-title fw-bold" id="exampleModalLabel">Scan Barcode</h5>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col text-center">
                        <div id="reader">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-3">
                <button type="button" class="btn btn-danger py-2 closeModalQrcode">Keluar</button>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- @push('style')
@endpush --}}

@push('style')
      <style>
		  /* @media (max-width: 800) {
			  .container-fluid {
				  padding-left: 0 !important;
				  padding-right: 0 !important;
			  }
		  } */
		  @media (min-width: 200px) {
			  .container-fluid {
				  padding-left: 2rem !important;
				  padding-right: 2rem !important;
			  }
		  }

		@media (min-width: 460px) { 
			.container-fluid {
				  padding-left: 2.5rem !important;
				  padding-right: 2.5rem !important;
			  }
		 }

		/* Medium devices (tablets, 768px and up) */
		@media (min-width: 768px) {
			.container-fluid {
				  padding-left: 3.5rem !important;
				  padding-right: 3.5rem !important;
			  }
		 }

		/* Large devices (desktops, 992px and up) */
		@media (min-width: 990px) {
			.container-fluid {
				  padding-left: 10rem !important;
				  padding-right: 10rem !important;
			  }
		 }

		/* Extra large devices (large desktops, 1200px and up) */
		@media (min-width: 1600px) {
			.container-fluid {
				  padding-left: 16rem !important;
				  padding-right: 16rem !important;
			  }
		}
		/* @media (min-width: 1600px) {
			.container-fluid {
				padding-left: 120px !important;
				padding-right: 120px !important;
			} */
		
     </style>
<style>
    .times {
        font-family: "Times New Roman", Times, serif;
    }

    table {
        border-collapse: collapse
    }

    .tab {
        display: inline-block;
        margin-left: 40px;
    }

    .tab2 {
        display: inline-block;
        margin-left: 55px;
    }

    .tab3 {
        display: inline-block;
        margin-left: 32px;
    }

    .tab4 {
        display: inline-block;
        margin-left: 70px;
    }

    .ttd {
        position: relative;
        overflow: visible;
    }

    .gambarTtd {
        position: absolute;
        top: 10px;
        left: 30px;
    }

    .cap {
        position: absolute;
        top: 0;
        left: 50px;
    }

    table {
        table-layout: fixed;
    }

    .dokumen p {
        font-size: 15px;
        margin-bottom: 0px !important;
    }

    .dokumen td {
        font-size: 15px;
        margin-bottom: 0px !important;
    }

    .tulisan-dokumen {
        font-size: 15px;
        margin-bottom: 0px !important;
    }


    .table-content td {
        border: 1px solid black;
    }

    .table-content th {
        border: 1px solid black;
    }

</style>
@endpush

@push('script')
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $('.modal').appendTo("body");

    $('#btnCari').click(function () {
        var idDokumen = $('#idDokumen').val();
        if (idDokumen != '') {
            $.ajax({
                type: "POST",
                url: "/get-cek-berkas",
                dataType: 'json',
                data: {
                    idDokumen: idDokumen
                },
                success: function (data) {
                    if (data.res == 'success') {
                        $('#surat').empty();
                        $('#surat').append(data.dataHtml);
                        $("#surat").addClass("border border-black");
                    } else {
                        $('#surat').empty();
                        $('#surat').append(data.dataHtml);
                        $("#surat").removeClass("border border-black");
                        Swal.fire(
                            'Berkas Tidak Ditemukan',
                            'Pastikan ID Dokumen Dimasukkan dengan benar',
                            'error'
                        )
                    }
                },
                error: function (data) {}
            });
        } else {
            Swal.fire(
                'Berkas Tidak Ditemukan',
                'Pastikan ID Dokumen Dimasukkan dengan benar',
                'error'
            )
        }
    })

    $('#btnQrcode').click(function () {
        $('#modalQrcode').modal('show');
        html5QrcodeScanner.render(onScanSuccess);
    })

    $('.closeModalQrcode').click(function () {
        $('#modalQrcode').modal('hide');
        html5QrcodeScanner.clear();
    })

    function onScanSuccess(qrCodeMessage) {
        $('#idDokumen').val(qrCodeMessage);
        html5QrcodeScanner.clear();
        $('#modalQrcode').modal('hide');
        $('#btnCari').click();
        // $('#kodeVoucher').val(qrCodeMessage);
        // $("#formSubmitVoucher").submit();
    }

    var html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", {
            fps: 10,
            qrbox: 250
        }
    );

</script>
@endpush
