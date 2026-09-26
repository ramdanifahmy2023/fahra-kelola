<?php

class Alert {
  
  // Set pesan alert ke session
  public static function set($type, $message) {
    $_SESSION['sys_alert'] = [
      'type'    => $type,
      'message' => $message
    ];
  }

  // Tampilkan alert jika ada di session
  public static function show() {
    if (isset($_SESSION['sys_alert'])) {
      $type = $_SESSION['sys_alert']['type'];
      $message = $_SESSION['sys_alert']['message'];
      
      // Default info
      $alertClass = 'alert-info bg-info text-info-content';
      $icon = 'info';
      
      // Sesuaikan warna dan ikon berdasarkan tipe
      if ($type == 'success') {
        $alertClass = 'alert-success bg-success text-success-content';
        $icon = 'check_circle';
      } elseif ($type == 'error' || $type == 'danger') {
        $alertClass = 'alert-error bg-error text-error-content';
        $icon = 'error';
      } elseif ($type == 'warning') {
        $alertClass = 'alert-warning bg-warning text-warning-content';
        $icon = 'warning';
      }

      // Render elemen Toast melayang di tengah atas
      echo '
      <div id="sys-toast-container" class="toast toast-top toast-center z-[9999] mt-4 transition-all duration-500 ease-in-out">
        <div class="alert ' . $alertClass . ' shadow-xl rounded-2xl font-medium flex items-center gap-3 px-6 py-4 border border-white/20">
          <span class="material-symbols-outlined text-[24px]">' . $icon . '</span>
          <span class="text-sm">' . $message . '</span>
          <button onclick="document.getElementById(\'sys-toast-container\').remove()" class="btn btn-sm btn-circle btn-ghost opacity-70 hover:opacity-100 ml-4">✕</button>
        </div>
      </div>
      
      <!-- Auto hide script -->
      <script>
        setTimeout(() => {
          const toast = document.getElementById("sys-toast-container");
          if(toast) {
            toast.style.opacity = "0";
            toast.style.transform = "translateY(-20px)";
            setTimeout(() => toast.remove(), 500);
          }
        }, 4000); // Hilang otomatis dalam 4 detik
      </script>
      ';

      // Hapus session setelah ditampilkan
      unset($_SESSION['sys_alert']);
    }
  }

}
