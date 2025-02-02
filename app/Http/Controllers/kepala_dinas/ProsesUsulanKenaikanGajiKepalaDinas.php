<?php

namespace App\Http\Controllers\kepala_dinas;

use App\Http\Controllers\Controller;
use App\Models\BerkasDasar;
use App\Models\BerkasUsulanGaji;
use App\Models\Persyaratan;
use App\Models\ProfileGuruPegawai;
use App\Models\User;
use App\Models\UsulanGaji;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Profiler\Profile;

class ProsesUsulanKenaikanGajiKepalaDinas extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = UsulanGaji::with(['user', 'profileGuruPegawai'])->orderBy('id', 'desc');
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $actionBtn = '<button type="button" class="btn btn-sm btn-primary lihatTimeline" id="' . $row->id . '">
                            Lihat
                        </button>';
                    return $actionBtn;
                })
                ->filter(function ($instance) use ($request) {
                    if ($request->statusBerkas != '') {
                        $instance->where('status_sekretaris', $request->statusBerkas);
                    }

                    if ($request->jenisAsn != '') {
                        $instance->whereHas('profileGuruPegawai', function ($profile) use ($request) {
                            $profile->where('jenis_asn', $request->jenisAsn);
                        });
                    }

                    if ($request->search != '') {
                        $instance->where('nama', "LIKE", "%$request->search%");
                    }
                })
                ->addColumn('daftarBerkas', function (UsulanGaji $usulanGaji) {
                    $daftarBerkas = '';
                    $i = 1;
                    foreach ($usulanGaji->berkasUsulanGaji as $berkasGaji) {
                        $daftarBerkas .= '<div class="d-block">
                                    <p>' . $i .  " . " . $berkasGaji->nama . '</p>
                                </div>';
                        $i++;
                    }
                    return $daftarBerkas;
                })
                ->addColumn('status', function ($row) {
                    if ($row->status_kepala_dinas == 0) {
                        $status = '<span class="badge badge-warning">Belum Diperiksa</span>';
                    } else if ($row->status_kepala_dinas == 1) {
                        $status = '<span class="badge badge-success">Selesai Diperiksa</span>';
                    } else {
                        $status = '<span class="badge badge-danger">Berkas Ditolak</span>';
                    }
                    return $status;
                })
                ->addColumn('tanggal', function ($row) {
                    return date('d-m-Y', strtotime($row->created_at));
                })
                ->addColumn('jenisAsn', function ($row) {
                    return $row->profileGuruPegawai->jenis_asn;
                })
                ->rawColumns(['action', 'status', 'daftarBerkas', 'tanggal', 'jenisAsn'])
                ->make(true);
        }

        return view('pages.kepala_dinas.kenaikanGaji.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\UsulanGaji  $usulanGaji
     * @return \Illuminate\Http\Response
     */
    public function show(UsulanGaji $usulanGaji)
    {
        $user = User::where('id', $usulanGaji->id_user)->first();
        $berkasDasar = BerkasDasar::where('id_user', $user->id)->get();
        $persyaratan = Persyaratan::with('deskripsiPersyaratan')->where('jenis_asn', $user->role)->where('kategori', 'Usulan Kenaikan Gaji Berkala')->get();
        return view('pages.kepala_dinas.kenaikanGaji.show', compact(['usulanGaji', 'user', 'berkasDasar', 'persyaratan']));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\UsulanGaji  $usulanGaji
     * @return \Illuminate\Http\Response
     */
    public function edit(UsulanGaji $usulanGaji)
    {
        if (!($usulanGaji->status_kepala_dinas != 0 && $usulanGaji->status_kepegawaian == 1 && $usulanGaji->status_kasubag == 1 && $usulanGaji->status_sekretaris == 1)) {
            return redirect()->route('proses-usulan-kenaikan-gaji-kepala-dinas.index');
        }
        $user = User::where('id', $usulanGaji->id_user)->first();
        $berkasDasar = BerkasDasar::where('id_user', $user->id)->get();
        $persyaratan = Persyaratan::with('deskripsiPersyaratan')->where('jenis_asn', $user->role)->where('kategori', 'Usulan Kenaikan Gaji Berkala')->get();
        return view('pages.kepala_dinas.kenaikanGaji.edit', compact(['usulanGaji', 'user', 'berkasDasar', 'persyaratan']));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\UsulanGaji  $usulanGaji
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, UsulanGaji $usulanGaji)
    {
        $profile = ProfileGuruPegawai::where('id_user', $usulanGaji->id_user)->first();
        $usulanGaji->status_kepala_dinas = $request->konfirmasi_berkas;
        $usulanGaji->tanggal_konfirmasi_kepala_dinas = now();
        $usulanGaji->nilai_gaji_selanjutnya = str_replace(".", "", $request->gaji_selanjutnya);
        if ($request->konfirmasi_berkas == 2) {
            $usulanGaji->alasan_tolak_kepala_dinas = $request->alasan_ditolak;
            $profile->tmt_gaji = $usulanGaji->tmt_gaji_sebelumnya;
            $profile->nilai_gaji = $usulanGaji->nilai_gaji_sebelumnya;
        } else if ($request->konfirmasi_berkas == 1) {
            $profile->tmt_gaji = $usulanGaji->tmt_gaji_selanjutnya;
            $profile->nilai_gaji = $usulanGaji->nilai_gaji_selanjutnya;
            $usulanGaji->alasan_tolak_kepala_dinas = NULL;

            $berkasDasarSkGaji = BerkasDasar::where('id_user', $usulanGaji->id_user)
                ->where('nama', 'SK Kenaikan Gaji Berkala')
                ->first();

            $berkasDasarSkPangkat = BerkasDasar::where('id_user', $usulanGaji->id_user)
                ->where('nama', 'SK Kenaikan Pangkat')
                ->first();

            $skGajiBerkala = BerkasUsulanGaji::where('id_usulan_gaji', $usulanGaji->id)
                ->where('nama', 'SK Gaji Berkala')
                ->first();

            $skPangkatTerakhir = BerkasUsulanGaji::where('id_usulan_gaji', $usulanGaji->id)
                ->where('nama', 'SK Pangkat Terakhir')
                ->first();

            Storage::delete('upload/berkas-dasar/' . $berkasDasarSkGaji->file);
            Storage::delete('upload/berkas-dasar/' . $berkasDasarSkPangkat->file);
            Storage::copy('upload/berkas-usulan-gaji/' . $skGajiBerkala->file, 'upload/berkas-dasar/' . $berkasDasarSkGaji->file);
            Storage::copy('upload/berkas-usulan-gaji/' . $skPangkatTerakhir->file, 'upload/berkas-dasar/' . $berkasDasarSkPangkat->file);
        }

        $totalUsulanGaji = UsulanGaji::count();
        $usulanGajiTerakhir = DB::table('usulan_gaji')->where('nomor_surat', '!=', NULL)->latest('id')->first();
        if (!$usulanGaji->nomor_surat) {
            if ($totalUsulanGaji == 1) {
                $usulanGaji->nomor_surat = 1;
            } else {
                $usulanGaji->nomor_surat = ($usulanGajiTerakhir->nomor_surat + 1);
            }
        }

        $usulanGaji->save();
        $profile->save();

        Toastr::success('Berhasil Memproses Berkas', 'Success');
        if (Auth::user()->role == "Kepala Dinas") {
            return redirect()->route('proses-usulan-kenaikan-gaji-kepala-dinas.index');
        } else if (Auth::user()->role == "Admin") {
            return redirect()->route('proses-usulan-kenaikan-gaji-admin.index');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\UsulanGaji  $usulanGaji
     * @return \Illuminate\Http\Response
     */
    public function destroy(UsulanGaji $usulanGaji)
    {
        //
    }

    public function getTimelineUsulanGaji(Request $request)
    {
        $id = $request->id;
        $usulanGaji = UsulanGaji::where('id', $id)->first();

        $startSection = '<section class="timeline_area section_padding_130">
                    <div class="container">
                        <div class="row">
                            <div class="col-12">
                                <!-- Timeline Area-->
                                <div class="apland-timeline-area">';

        $endSection = '
                            </div>
                        </div>
                    </div>
                </section>';

        // Timeline Guru
        $timelineGuru = '<div class="single-timeline-area">
                                        <div class="timeline-date timeline-date-accept wow fadeInLeft"
                                            data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p class="text-center">' . date('d-m-Y H:i:s', strtotime($usulanGaji->created_at)) . '</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon-accept"><i
                                                            class="fas fa-check"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Guru/Pegawai</h6>
                                                        <p>Berkas Selesai Diupload</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>';

        // Timeline Kepegawaian
        if ($usulanGaji->status_kepegawaian == 0) {
            $statusKepegawaian = '<div class="timeline-date wow fadeInLeft" data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p>---</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon"><i
                                                            class="far fa-clock"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Admin Kepegawaian</h6>
                                                        <p>Berkas Masih Diproses</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        } else if ($usulanGaji->status_kepegawaian == 1) {
            $statusKepegawaian = '<div class="timeline-date timeline-date-accept wow fadeInLeft"
                                            data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p class="text-center">' . date('d-m-Y H:i:s', strtotime($usulanGaji->tanggal_konfirmasi_kepegawaian))  . '</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon-accept"><i
                                                            class="fas fa-check"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Admin Kepegawaian</h6>
                                                        <p>Menyetujui Berkas</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        } else {
            $statusKepegawaian = '<div class="timeline-date timeline-date-reject wow fadeInLeft"
                                            data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p class="text-center">' . date('d-m-Y H:i:s', strtotime($usulanGaji->tanggal_konfirmasi_kepegawaian))  . '</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon-reject"><i
                                                            class="fas fa-times"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Admin Kepegawaian</h6>
                                                        <p>Berkas Ditolak</p>
                                                        <p>Alasan : ' . $usulanGaji->alasan_tolak_kepegawaian . '</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        }
        $timelineKepegawaian = '<div class="single-timeline-area">
                                    ' . $statusKepegawaian . '
                                    </div>';

        // Timeline Admin Kasubag
        if ($usulanGaji->status_kasubag == 0) {
            $statusKasubag = '<div class="timeline-date wow fadeInLeft" data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p>---</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon"><i
                                                            class="far fa-clock"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Kasubag Kepegawaian</h6>
                                                        <p>Berkas Masih Diproses</p>


                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        } else if ($usulanGaji->status_kasubag == 1) {
            $statusKasubag = '<div class="timeline-date timeline-date-accept wow fadeInLeft"
                                            data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p class="text-center">' . date('d-m-Y H:i:s', strtotime($usulanGaji->tanggal_konfirmasi_kasubag))  . '</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon-accept"><i
                                                            class="fas fa-check"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Kasubag Kepegawaian</h6>
                                                        <p>Menyetujui Berkas</p>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        } else {
            $statusKasubag = '<div class="timeline-date timeline-date-reject wow fadeInLeft"
                                            data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p class="text-center">' . date('d-m-Y H:i:s', strtotime($usulanGaji->tanggal_konfirmasi_kasubag))  . '</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon-reject"><i
                                                            class="fas fa-times"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Kasubag Kepegawaian</h6>
                                                        <p>Berkas Ditolak</p>
                                                        <p>Alasan : ' . $usulanGaji->alasan_tolak_kasubag . '</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        }
        $timelineKasubag = '<div class="single-timeline-area">
                                    ' . $statusKasubag . '
                                    </div>';

        // Timeline Sekretaris
        if ($usulanGaji->status_sekretaris == 0) {
            $statusSekretaris = '<div class="timeline-date wow fadeInLeft" data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p>---</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon"><i
                                                            class="far fa-clock"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Sekretaris</h6>
                                                        <p>Berkas Masih Diproses</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        } else if ($usulanGaji->status_sekretaris == 1) {
            $statusSekretaris = '<div class="timeline-date timeline-date-accept wow fadeInLeft"
                                            data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p class="text-center">' . date('d-m-Y H:i:s', strtotime($usulanGaji->tanggal_konfirmasi_sekretaris))  . '</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon-accept"><i
                                                            class="fas fa-check"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Sekretaris</h6>
                                                        <p>Menyetujui Berkas</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        } else {
            $statusSekretaris = '<div class="timeline-date timeline-date-reject wow fadeInLeft"
                                            data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p class="text-center">' . date('d-m-Y H:i:s', strtotime($usulanGaji->tanggal_konfirmasi_sekretaris))  . '</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon-reject"><i
                                                            class="fas fa-times"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Sekretaris</h6>
                                                        <p>Berkas Ditolak</p>
                                                        <p>Alasan : ' . $usulanGaji->alasan_tolak_sekretaris . '</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        }
        $timelineSekretaris = '<div class="single-timeline-area">
                                    ' . $statusSekretaris . '
                                    </div>';

        // Timeline Kepala Dinas
        $btnUbah = '';
        $btnProses = '';
        if ($usulanGaji->status_kepala_dinas != 0) {
            $btnUbah = '<a href=" ' . route('proses-usulan-kenaikan-gaji-kepala-dinas.edit', $usulanGaji->id) . '" class="btn btn-sm btn-warning mt-2">Ubah Konfirmasi</a>';
        }

        if ($usulanGaji->status_kasubag == 1 && $usulanGaji->status_kepala_dinas == 0 && $usulanGaji->status_kepegawaian == 1 && $usulanGaji->status_sekretaris == 1) {
            $btnProses = '<div class="row"><a href=" ' . url('proses-berkas-usulan-kenaikan-gaji-kepala-dinas', $usulanGaji->id) . ' " class="btn btn-sm btn-success mt-2 mr-2 ml-3">Proses Berkas</a></div>';
        }
        if ($usulanGaji->status_kepala_dinas == 0) {
            $statusKepalaDinas = '<div class="timeline-date wow fadeInLeft" data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p>---</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon"><i
                                                            class="far fa-clock"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Kepala Dinas</h6>
                                                        <p>Berkas Masih Diproses</p>
                                                        <div class="row">
                                                        <a href=" ' . route('proses-usulan-kenaikan-gaji-kepala-dinas.show', $usulanGaji->id) . '" class="btn btn-sm btn-primary mt-2 mr-1 ml-3">Lihat Berkas</a>
                                                        ' . $btnProses . '
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        } else if ($usulanGaji->status_kepala_dinas == 1) {
            $statusKepalaDinas = '<div class="timeline-date timeline-date-accept wow fadeInLeft"
                                            data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p class="text-center">' . date('d-m-Y H:i:s', strtotime($usulanGaji->tanggal_konfirmasi_kepala_dinas))  . '</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon-accept"><i
                                                            class="fas fa-check"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Kepala Dinas</h6>
                                                        <p>Menyetujui Berkas</p>
                                                        <div class="row">
                                                        <a href=" ' . route('proses-usulan-kenaikan-gaji-kepala-dinas.show', $usulanGaji->id) . '" class="btn btn-sm btn-primary mt-2 mr-1 ml-3">Lihat Berkas</a>
                                                        ' . $btnUbah . '
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        } else {
            $statusKepalaDinas = '<div class="timeline-date timeline-date-reject wow fadeInLeft"
                                            data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p class="text-center">' . date('d-m-Y H:i:s', strtotime($usulanGaji->tanggal_konfirmasi_kepala_dinas))  . '</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon-reject"><i
                                                            class="fas fa-times"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Kepala Dinas</h6>
                                                        <p>Berkas Ditolak</p>
                                                        <p>Alasan : ' . $usulanGaji->alasan_tolak_kepala_dinas . '</p>
                                                        <div class="row">
                                                        <a href=" ' . route('proses-usulan-kenaikan-gaji-kepala-dinas.show', $usulanGaji->id) . '" class="btn btn-sm btn-primary mt-2 mr-1 ml-3">Lihat Berkas</a>
                                                        ' . $btnUbah . '
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        }
        $timelineKepalaDinas = '<div class="single-timeline-area">
                                    ' . $statusKepalaDinas . '
                                    </div>';

        // Timeline Berkas
        if ($usulanGaji->status_kepala_dinas == 0 || $usulanGaji->status_kepala_dinas == 2) {
            $statusBerkas = '<div class="timeline-date wow fadeInLeft" data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p>---</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon"><i
                                                            class="far fa-clock"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Unduh Berkas</h6>
                                                        <p>Berkas Masih Diperiksa</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        } else if ($usulanGaji->status_kepala_dinas == 1) {
            $statusBerkas = '<div class="timeline-date timeline-date-accept wow fadeInLeft"
                                            data-wow-delay="0.1s"
                                            style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInLeft;">
                                            <p class="text-center">' . date('d-m-Y H:i:s', strtotime($usulanGaji->tanggal_konfirmasi_kepala_dinas))  . '</p>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="single-timeline-content d-flex wow fadeInLeft"
                                                    data-wow-delay="0.3s"
                                                    style="visibility: visible; animation-delay: 0.3s; animation-name: fadeInLeft;">
                                                    <div class="timeline-icon timeline-icon-accept"><i
                                                            class="fas fa-check"></i></div>
                                                    <div class="timeline-text">
                                                        <h6>Unduh Surat Pengantar Kenaikan Gaji</h6>
                                                        <div class="row">
                                                        <a href="' . url('cetak-usulan-kenaikan-gaji', $usulanGaji->id) . '"class="btn btn-sm btn-success mt-2 mr-2 ml-3">Unduh Surat</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
        };
        $timelineBerkas = '<div class="single-timeline-area">
                                    ' . $statusBerkas . '
                                    </div>';

        $timeline = $startSection . $timelineGuru . $timelineKepegawaian . $timelineKasubag . $timelineSekretaris . $timelineKepalaDinas . $timelineBerkas . $endSection;
        return response()->json([
            'res' => 'success',
            'html' => $timeline
        ]);
    }

    public function prosesBerkas(UsulanGaji $usulanGaji)
    {
        if (!($usulanGaji->status_kepala_dinas == 0 && $usulanGaji->status_kepegawaian == 1 && $usulanGaji->status_kasubag == 1 && $usulanGaji->status_sekretaris == 1)) {
            return redirect()->route('proses-usulan-kenaikan-gaji-kepala-dinas.index');
        }
        $user = User::where('id', $usulanGaji->id_user)->first();
        $berkasDasar = BerkasDasar::where('id_user', $user->id)->get();
        $persyaratan = Persyaratan::with('deskripsiPersyaratan')->where('jenis_asn', $user->role)->where('kategori', 'Usulan Kenaikan Gaji Berkala')->get();
        return view('pages.kepala_dinas.kenaikanGaji.proses', compact(['usulanGaji', 'user', 'berkasDasar', 'persyaratan']));
    }
}
