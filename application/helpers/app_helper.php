<?php

function flip_tanggal($prm_tgl, $separator1 = '/', $separator2 = '/'){
	$tgl = explode($separator1, $prm_tgl);
	return $tgl[2] . $separator2 . $tgl[1] . $separator2 . $tgl[0];
}

// mengambil isi dari elemen DOM
function get_inner_html($node) {
    $innerHTML= '';
    $children = $node->childNodes;
    
    foreach ($children as $child){
        $innerHTML .= $child->ownerDocument->saveXML( $child );
    }
    
    return $innerHTML;
}

// otentikasi ke webservice
function login_webservice($login, $password){
	$CI =& get_instance();
    $acc_login = get_app_config('LOGIN_SERVICE');
    $acc_pass = get_app_config('PASS_SERVICE');
	return(($login == $acc_login) && ($password = $acc_pass));
}

// String unik dengan panjang tertentu
function string_acak($pjg) {
    $char = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $char = str_shuffle($char);
    for($i = 0, $rand = '', $l = strlen($char) - 1; $i < $pjg; $i ++) {
        $rand .= $char{mt_rand(0, $l)};
    }
    return $rand;
}

// penambahan tanggal dengan sejumlah menit tertentu
function interval_tgl($tgl_mulai, $interval){
	$time = new DateTime($tgl_mulai);
	$time->add(new DateInterval('PT' . $interval . 'M'));
	return $time->format('Y-m-d H:i');
}

// konversi kode jenis ujian
function jenis_ujian($kode){
    $arr = array('0' => 'Ulangan Harian', '1' => 'USBN');
    return $arr[$kode];
}

// konversi digit bulan menjadi string
function bulan($digit){
    $arr = array(
        '01'=> 'Januari',
        '02'=> 'Februari',
        '03'=> 'Maret',
        '04'=> 'April',
        '05'=> 'Mei',
        '06'=> 'Juni',
        '07'=> 'Juli',
        '08'=> 'Agustus',
        '09'=> 'September',
        '10'=> 'Oktober',
        '11'=> 'Nopember',
        '12'=> 'Desember',
    );
    return $arr[$digit];
}

// konversi tgl mysql ke string
function mysqldate_to_str($date){
    $tmp = explode(' ', $date);
    $tgl = flip_tanggal($tmp[0],'-', '-');
    $jam = $tmp[1];
    
    $dmy = explode('-', $tgl);
    return $dmy[0] . ' ' . bulan($dmy[1]) . ' ' . $dmy[2] . ', ' . $jam;
}

// deteksi menu css yang aktif
function css_class_active($class, $get = 'c'){
    $CI =& get_instance();
    if($CI->input->get($get) == $class){
        return 'active';
    }else{
        return '';
    }
}

// Ambil nilai konfigurasi dari tabel
function get_app_config($id){
    $CI =& get_instance();
    $CI->db->select('nilai_konfig');
    $CI->db->where('konfig_id', $id);
    return $CI->db->get('konfig')->row()->nilai_konfig;
}

// Atur nilai konfigurasi
function modif_app_config($id, $val){
    $CI =& get_instance();
    $CI->db->where('konfig_id', $id);
    $CI->db->set('nilai_konfig', $val);
    return $CI->db->update('konfig');
}

// Menghasilkan output json
function json_output($statusHeader,$response){
    $ci =& get_instance();
    $ci->output->set_content_type('application/json');
    $ci->output->set_status_header($statusHeader);
    $ci->output->set_output(json_encode($response));
}

function arsipkan_folder($dir, $zip_file = 'file.zip'){
    
    
    // Get real path for our folder
    $rootPath = realpath($dir);
    
    // Initialize archive object
    $zip = new ZipArchive();
    $zip->open($zip_file, ZipArchive::CREATE);
    
    // Create recursive directory iterator
    /** @var SplFileInfo[] $files */
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $name => $file)
    {
        // Skip directories (they would be added automatically)
        if (!$file->isDir())
        {
            // Get real and relative path for current file
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);
            
            // echo $filePath . '<br>';
            // echo $relativePath . '<br>';
            
            // Add current file to archive
            $zip->addFile($filePath, $relativePath);
        }
    }
    
    // Zip archive will be created only after closing object
    $zip->close();
    
    
}

function salin_folder($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                salin_folder($src . '/' . $file,$dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function hapus_folder($dir){
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it,
    RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) {
        if ($file->isDir()){
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($dir);
}