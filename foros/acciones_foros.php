
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status'=>'error','msg'=>'acciones_foros no está configurado.']);
