<?php if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}
require FCPATH . 'vendor/autoload.php';
use \Curl\Curl;

require_once APPPATH . 'controllers/proktor/Home_proktor.php';

class Sinkron extends Home_proktor{
  
  function __construct(){
    parent::__construct();
    $this->token = 'XdbtHR6p5msLvCNk';
    ini_set('memory_limit', '512M');
  }
  
  function tarik(){
    $this->load->view('proktor/sinkron/tarik');
  }
  
  function do_tarik(){
    ini_set('max_execution_time', 0); 
    $post = $this->input->post();
    $target = $post['server_remote'] . '/index.php?c=sinkron&m=tarik';
    $data = [
      'token' => $this->token,
      'id_server' => $post['id_server']
    ];
    
    // kirim request ke server remote
    $curl = new Curl();
    $curl->post($target, $data);
    
    if($curl->getHttpStatusCode() == 200){
      
      $r = $curl->response;
      
      if(is_string($r)) $r = json_decode($r);

      if(!isset($r->pesan)){
        myob('<p>Gagal tersambung ke server</p><p><a href="?d=proktor&c=sinkron&m=tarik">kembali</a></p>');
        die();
      }

      if($r->pesan == 'token_gagal'){
        myob('<p>Token gagal, server lokal tidak diijinkan untuk melakukan syncx</p><p><a href="?d=proktor&c=sinkron&m=tarik">kembali</a></p>');
        die();
      }elseif($r->pesan == 'server_gagal'){
        myob('<p>Server '. $post['id_server'] .' tidak tersedia</p><p><a href="?d=proktor&c=sinkron&m=tarik">kembali</a></p>');
        die();
      }else{
        myob('<p>Sedang mengunduh, mohon tunggu, proses ini memerlukan waktu, bergantung dari koneksi... '. $r->nama_zip .' </p>');
        // json_output(200, array('pesan' => 'ok', 'nama_zip' => $r->nama_zip));
        $full_path_zip = $post['server_remote']  . '/index.php?c=sinkron&m=tarik_zip&zip=' . $r->nama_zip;
        $nama_sinkron = FCPATH . 'public/sinkron_' . $r->nama_zip;
        myob('<p>Sedang mengunduh, mohon tunggu, proses ini memerlukan waktu, bergantung dari koneksi... '. $r->nama_zip .' </p>');
        file_put_contents($nama_sinkron, fopen($full_path_zip, 'r'));

        myob('<p>Sinkronisasi password ...</p>');
        $this->__sync_pass_bank($r->password_bank);
        modif_app_config('LOGIN_SERVICE', $r->LOGIN_SERVICE);
        modif_app_config('PASS_SERVICE', $r->PASS_SERVICE);

        myob('<p>Mengekstrak data sinkron ...</p>');
        $this->__do_restore($nama_sinkron, $r->id_log);

      }
    }else{
      myob('<p>Koneksi dengan server remote gagal</p>');
    }
  }
  
  private function __do_restore($fullpath_arsip_sinkron, $id_log){
    $post = $this->input->post();

    // 1. Reset data
    myob('<p>Reset data soal lama...</p>');
    data_do_reset(false, $post['id_server'] != '');
    
    // 2. ekstrak backup
    myob('<p>Mengekstrak berkas sinkronisasi...</p>');
    $ekstrak_path = FCPATH . 'public/tmp-sinkron';
    $berhasil_ekstrak = ekstrak_zip($fullpath_arsip_sinkron, $ekstrak_path);
    
    // 3. pindahkan gambar
    myob('<p>Sedang menyalin gambar...</p>');
    rcopy($ekstrak_path . '/images', FCPATH . 'images');
    
    // 4. baca data json, sekaligus masukkan ke database
    $string = file_get_contents($ekstrak_path . '/data.json');
    $data = json_decode($string, true);
    myob('<p>Sedang menyalin database...</p>');
    data_do_pemulihan_data($data, isset($post['server_remote']));
    
    // 5. hapus sisa backup yg tak diperlukan lagi
    myob('<p>menghapus sisa backup ...</p>');
    unlink($fullpath_arsip_sinkron);
    rrmdir($ekstrak_path);

    myob('<p>Proses backup telah selesai log : '. $id_log .'</p>');
    // catat log sukses di server
    $curl = new Curl();
    $data = ['token' => $this->token, 'id_log' => $id_log];
    $curl->post($post['server_remote'] . '/index.php?c=sinkron&m=log_tarik_sukses', $data);

    sleep(3);
    echo '<script>document.location.href="?d=proktor&c=sinkron&m=tarik"</script>';

  }

  private function __sync_pass_bank($password_bank){
    myob('<p>Sinkonisasi password bank soal...  </p>');
    $this->load->library('encryption');
    $password_bank = $this->encryption->decrypt($password_bank);
    $password_bank = $this->encryption->encrypt($password_bank);
    $db_bank = $this->load->database('bank', TRUE);
    $db_bank->set('password', $password_bank);
    $db_bank->where('login',  'bank');
    $db_bank->update('pengguna');
  }
  
  function kirim(){
    $sql = "SELECT * FROM ujian WHERE status_soal <> 0";
    $data['ujian'] = $this->db->query($sql)->result();
    $this->load->view('proktor/sinkron/kirim', $data);
  }
  
  function do_kirim(){
    set_time_limit(0);

    $post = $this->input->post();
    $target = $post['server_remote'] . '/index.php?c=sinkron&m=terima_nilai_gz';
    $sql_jawaban = "SELECT a.*
    FROM peserta_jawaban a
    LEFT JOIN peserta b 
    ON a.ujian_id = b.ujian_id
    AND a.nis = b.nis
    AND a.login = b.login
    WHERE b.server  = '$post[id_server]'
    AND b.ujian_id = '$post[ujian_id]'";

    $data_peserta = json_encode($this->db->get_where('peserta', array('server' => $post['id_server'], 'ujian_id' => $post['ujian_id']))->result());
    $data_peserta_jawaban = json_encode($this->db->query($sql_jawaban)->result());
    $data_peserta = base64_encode(gzdeflate($data_peserta));
    $data_peserta_jawaban = base64_encode(gzdeflate($data_peserta_jawaban));

    $data = [
      'token' => $this->token,
      'id_server' => $post['id_server'],
      'ujian_id' => $post['ujian_id'],
      'peserta' => $data_peserta,
      'peserta_jawaban' => $data_peserta_jawaban,
    ];


    // kirim request ke server remote
    $curl = new Curl();
    $curl->post($target, $data);
    if($curl->getHttpStatusCode() == 200){
      $r = $curl->response;
      json_output(200, ['pesan'           => 'ok', 
                        'rincian'        => json_decode($r)]);
    }else{
      json_output(200, array('pesan' => 'konek_gagal', 'resp' => $curl->getHttpStatusCode()));
    }

  }
  
}