<?php

$logo = __DIR__ . '/assets/image/logo.png';

if(file_exists($logo)){
    echo "Logo ditemukan";
}else{
    echo "Logo tidak ditemukan";
}

?>