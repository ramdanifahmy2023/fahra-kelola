<!DOCTYPE html>
<html lang="id" data-theme="cupcake">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $data['judul'] ?? app_name; ?></title>
  
  <!-- CSS Tailwind & DaisyUI -->
  <link rel="stylesheet" href="<?= assets; ?>/css/style.css">
  
  <!-- Google Material Symbols -->
  <link rel="stylesheet" href="<?= web_icons; ?>/material-symbols.css">
</head>
<body class="min-h-screen bg-base-200 flex flex-col">
