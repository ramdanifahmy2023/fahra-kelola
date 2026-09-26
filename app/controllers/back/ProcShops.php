<?php

class ProcShops extends Controller {
  
  public function add() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      $cookie = trim($_POST['cookie'] ?? '');
      $email  = trim($_POST['email'] ?? '');

      if (empty($cookie) || empty($email)) {
        Alert::set('warning', 'Data form tidak lengkap.');
        header('Location: ' . burl . '/panel/shops');
        exit;
      }

      // Tembak endpoint verifikasi Shopee
      $shopeeResponse = $this->m('ShopeeCurl')->check($cookie);

      // Verifikasi respons, pastikan ada 'shop' dan 'id' yang tertangkap
      if (isset($shopeeResponse['shop']) && !empty($shopeeResponse['shop']['id'])) {
        
        $userInfo = $this->m('ShopeeCurl')->getUserInfo($cookie);
        $shopLogo = isset($userInfo['portrait']) && !empty($userInfo['portrait']) ? 'https://cf.shopee.co.id/file/' . $userInfo['portrait'] : images . '/app_brands/shopee.png';

        $data = [
          'account_id' => 1, // Hardcode sementara sampai fitur login ada
          'shop_id'    => $shopeeResponse['shop']['id'],
          'name'       => $shopeeResponse['shop']['name'],
          'shop_logo'  => $shopLogo,
          'username'   => $userInfo['user_name'] ?? '',
          'email'      => $email,
          'cookie'     => $cookie,
          'sync_status' => 'connected'
        ];

        // Coba ambil alamat pickup
        $address = $this->m('ShopeeCurl')->getAddress($cookie);
        if (!empty($address)) {
            $data['address'] = $address;
        }

        // Ambil data Saldo Wallet
        $wallet = $this->m('ShopeeCurl')->getWallet($cookie);
        if ($wallet !== false) {
            $data['balances'] = $wallet;
        }

        // Ambil data Kredit Iklan
        $ads = $this->m('ShopeeCurl')->getAdsData($cookie);
        if ($ads !== false && isset($ads['total'])) {
            $data['ads_credit'] = $ads['total'];
        }

        $insert = $this->m('Shop')->insert($data);

        if ($insert > 0) {
          Alert::set('success', 'Berhasil! Toko ' . htmlspecialchars($data['name']) . ' berhasil disinkronkan dan ditambahkan.');
        } else {
          Alert::set('error', 'Gagal menyimpan ke database meskipun sinkronisasi sukses.');
        }
      } else {
        // Cookie ditolak Shopee
        $pesanError = $shopeeResponse['message'] ?? 'Cookie tidak valid atau sesi login Shopee sudah kadaluarsa.';
        Alert::set('error', 'Gagal Sinkron! ' . $pesanError);
      }

      header('Location: ' . burl . '/panel/shops');
      exit;
    }
  }
  // Hapus Toko
  public function delete($id = null) {
    if (!empty($id)) {
      $delete = $this->m('Shop')->delete($id);
      if ($delete > 0) {
        Alert::set('success', 'Berhasil! Toko telah dihapus dari sistem.');
      } else {
        Alert::set('error', 'Gagal! Terjadi kesalahan saat menghapus toko.');
      }
    }
    header('Location: ' . burl . '/panel/shops');
    exit;
  }

  // Update & Sync Seluruh Data Toko
  public function update($id = null) {
    if (!empty($id)) {
      // Ambil data toko lama dari database untuk mendapatkan cookie
      $shop = $this->m('Shop')->findBy('id', $id);
      
      $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

      if (!$shop || empty($shop['cookie'])) {
          if ($isAjax) {
              echo json_encode(['status' => 'error', 'message' => 'Gagal! Data toko tidak ditemukan atau cookie kosong.']);
              exit;
          }
          Alert::set('error', 'Gagal! Data toko tidak ditemukan atau cookie kosong.');
          header('Location: ' . burl . '/panel/shops');
          exit;
      }

      $cookie = $_POST['cookie'] ?? $shop['cookie'];
      $data = [];
      if (isset($_POST['cookie'])) {
          $data['cookie'] = $_POST['cookie'];
      }

      // Tangkap data dari POST (jika dikirim via form edit)
      $allowed_columns = ['email', 'phone', 'password'];
      if ($_SERVER['REQUEST_METHOD'] == 'POST') {
          foreach ($allowed_columns as $col) {
              if (isset($_POST[$col])) {
                  $data[$col] = $_POST[$col];
              }
          }
      }

      // Sinkronisasi dengan API Shopee
      $shopee = $this->m('ShopeeCurl');
      
      // 1. Cek info dasar (Shop ID, Name, Logo)
      $check = $shopee->check($cookie);
      if (isset($check['shop']) && !empty($check['shop']['id'])) {
          $data['shop_id'] = $check['shop']['id'];
          $data['name']    = $check['shop']['name'];
          
          $userInfo = $shopee->getUserInfo($cookie);
          $data['shop_logo'] = isset($userInfo['portrait']) && !empty($userInfo['portrait']) ? 'https://cf.shopee.co.id/file/' . $userInfo['portrait'] : images . '/app_brands/shopee.png';
          $data['username'] = $userInfo['user_name'] ?? '';
          
          $data['sync_status'] = 'connected';
      } else {
          // Jika gagal merespons, berarti cookie kemungkinan sudah mati
          $data['sync_status'] = 'expired';
      }

      // 2. Jika cookie masih aktif, ambil data ekstra (Alamat & Saldo)
      if (isset($data['sync_status']) && $data['sync_status'] === 'connected') {
          // Sinkronisasi Alamat
          $address = $shopee->getAddress($cookie);
          if (!empty($address)) {
              $data['address'] = $address;
          }

          // Sinkronisasi Saldo Wallet
          $wallet = $shopee->getWallet($cookie);
          if ($wallet !== false) {
              $data['balances'] = $wallet;
          }

          // Sinkronisasi Kredit Iklan
          $ads = $shopee->getAdsData($cookie);
          if ($ads !== false && isset($ads['total'])) {
              $data['ads_credit'] = $ads['total'];
          }
      }

      // Eksekusi Update ke Database
      $update = $this->m('Shop')->update($id, $data);

      $msg = ($update > 0) ? 'Berhasil! Data toko telah disinkronkan dan diperbarui dari Shopee.' : 'Sinkronisasi selesai (Data sudah paling mutakhir).';

      if ($isAjax) {
          echo json_encode(['status' => 'success', 'message' => $msg]);
          exit;
      }

      if ($update > 0) {
        Alert::set('success', $msg);
      } else {
        Alert::set('success', $msg);
      }
    }
    
    header('Location: ' . burl . '/panel/shops');
    exit;
  }

  // Update Khusus Cookie Toko
  public function update_cookie($id = null) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($id)) {
      $data = [
        'cookie' => $_POST['cookie'] ?? ''
      ];

      $update = $this->m('Shop')->update($id, $data);

      if ($update > 0) {
        Alert::set('success', 'Berhasil! Cookie toko telah diperbarui.');
      } else {
        Alert::set('error', 'Gagal! Terjadi kesalahan saat memperbarui cookie.');
      }
    }
    header('Location: ' . burl . '/panel/shops');
    exit;
  }

}
