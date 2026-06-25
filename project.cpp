#include <iostream>
#include <string>
#include <iomanip>
#include <queue>
#include <stack>

using namespace std;

// --- STRUKTUR DATA ---
struct Buku {
    string kode;
    string judul;
    string penulis;
    int tahun;
    bool tersedia;
    string peminjam; // Tambahan untuk menyimpan nama peminjam buku ini
};

// --- VARIABEL GLOBAL ---
const int MAKSIMAL_BUKU = 100;
Buku daftarBuku[MAKSIMAL_BUKU];
int jumlahBuku = 0;

queue<string> antrianPeminjam; // Queue untuk antrian peminjaman
stack<string> riwayatPengembalian; // Stack untuk riwayat buku yang dikembalikan

// --- 1. FUNGSI TAMBAH BUKU ---
void tambahBuku() {
    if (jumlahBuku >= MAKSIMAL_BUKU) {
        cout << "Kapasitas perpustakaan penuh!\n";
        return;
    }
    cout << "\n--- Tambah Buku Baru ---\n";
    cout << "Kode Buku : "; cin >> daftarBuku[jumlahBuku].kode;
    cin.ignore();
    cout << "Judul     : "; getline(cin, daftarBuku[jumlahBuku].judul);
    cout << "Penulis   : "; getline(cin, daftarBuku[jumlahBuku].penulis);
    cout << "Tahun     : "; cin >> daftarBuku[jumlahBuku].tahun;
    daftarBuku[jumlahBuku].tersedia = true; 
    daftarBuku[jumlahBuku].peminjam = "-"; // Default tidak ada peminjam
    jumlahBuku++;
    cout << "Buku berhasil ditambahkan!\n";
}

// --- 2. FUNGSI TAMPILKAN BUKU (MODIFIKASI KOLOM STOK & PEMINJAM) ---
void tampilkanBuku() {
    cout << "\n=========================================================================================\n";
    cout << left << setw(10) << "Kode" << setw(22) << "Judul" << setw(15) << "Penulis" << setw(10) << "Tahun" << setw(15) << "Status/Stok" << setw(15) << "Peminjam" << "\n";
    cout << "=========================================================================================\n";
    if (jumlahBuku == 0) {
        cout << "Tidak ada data buku.\n";
    } else {
        for (int i = 0; i < jumlahBuku; i++) {
            string status = daftarBuku[i].tersedia ? "Tersedia" : "Habis (0)";
            cout << left << setw(10) << daftarBuku[i].kode 
                 << setw(22) << daftarBuku[i].judul 
                 << setw(15) << daftarBuku[i].penulis 
                 << setw(10) << daftarBuku[i].tahun
                 << setw(15) << status
                 << setw(15) << daftarBuku[i].peminjam << "\n";
        }
    }
    cout << "=========================================================================================\n";
}

// --- 3. FUNGSI PENCARIAN BUKU (SEQUENTIAL SEARCH) ---
void cariBuku() {
    string keyword;
    cout << "\nMasukkan Kode atau Judul Buku yang dicari: ";
    cin.ignore();
    getline(cin, keyword);
    
    bool ditemukan = false;
    cout << "\nHasil Pencarian:\n";
    for (int i = 0; i < jumlahBuku; i++) {
        if (daftarBuku[i].kode == keyword || daftarBuku[i].judul == keyword) {
            cout << "Kode: " << daftarBuku[i].kode << " | Judul: " << daftarBuku[i].judul 
                 << " | Penulis: " << daftarBuku[i].penulis << " | Tahun: " << daftarBuku[i].tahun 
                 << " | Status: " << (daftarBuku[i].tersedia ? "Tersedia" : "Dipinjam oleh " + daftarBuku[i].peminjam) << "\n";
            ditemukan = true;
        }
    }
    if (!ditemukan) cout << "Buku tidak ditemukan.\n";
}

// --- 4. FUNGSI UBAH DATA BUKU ---
void ubahBuku() {
    string kode;
    cout << "\nMasukkan Kode Buku yang ingin diubah: ";
    cin >> kode;
    
    for (int i = 0; i < jumlahBuku; i++) {
        if (daftarBuku[i].kode == kode) {
            cin.ignore();
            cout << "Masukkan Judul Baru   : "; getline(cin, daftarBuku[i].judul);
            cout << "Masukkan Penulis Baru : "; getline(cin, daftarBuku[i].penulis);
            cout << "Masukkan Tahun Baru   : "; cin >> daftarBuku[i].tahun;
            cout << "Data buku berhasil diubah!\n";
            return;
        }
    }
    cout << "Buku dengan kode tersebut tidak ditemukan.\n";
}

// --- 5. FUNGSI HAPUS BUKU ---
void hapusBuku() {
    string kode;
    cout << "\nMasukkan Kode Buku yang ingin dihapus: ";
    cin >> kode;
    
    for (int i = 0; i < jumlahBuku; i++) {
        if (daftarBuku[i].kode == kode) {
            for (int j = i; j < jumlahBuku - 1; j++) {
                daftarBuku[j] = daftarBuku[j + 1];
            }
            jumlahBuku--;
            cout << "Buku berhasil dihapus!\n";
            return;
        }
    }
    cout << "Buku tidak ditemukan.\n";
}

// --- 6. FUNGSI PINJAM BUKU (MODIFIKASI UNTUK MENYIMPAN NAMA PEMINJAM KE BUKU) ---
void pinjamBuku() {
    string namaPeminjam, kodeBuku;
    cout << "\nNama Peminjam : ";
    cin.ignore();
    getline(cin, namaPeminjam);
    cout << "Kode Buku yang dipinjam : ";
    cin >> kodeBuku;

    for (int i = 0; i < jumlahBuku; i++) {
        if (daftarBuku[i].kode == kodeBuku) {
            if (daftarBuku[i].tersedia) {
                daftarBuku[i].tersedia = false;
                daftarBuku[i].peminjam = namaPeminjam; // Menyimpan nama peminjam langsung pada objek buku
                antrianPeminjam.push(namaPeminjam);
                cout << "Peminjaman berhasil! " << namaPeminjam << " masuk dalam antrian peminjaman.\n";
            } else {
                cout << "Maaf, buku sedang dipinjam.\n";
            }
            return;
        }
    }
    cout << "Buku tidak ditemukan.\n";
}

// --- 7. FUNGSI KEMBALIKAN BUKU (MODIFIKASI UNTUK MENGHAPUS NAMA PEMINJAM) ---
void kembalikanBuku() {
    string kodeBuku;
    cout << "\nMasukkan Kode Buku yang dikembalikan: ";
    cin >> kodeBuku;

    for (int i = 0; i < jumlahBuku; i++) {
        if (daftarBuku[i].kode == kodeBuku) {
            if (!daftarBuku[i].tersedia) {
                daftarBuku[i].tersedia = true;
                riwayatPengembalian.push(daftarBuku[i].judul); 
                
                if(!antrianPeminjam.empty()){
                    cout << antrianPeminjam.front() << " telah selesai meminjam.\n";
                    antrianPeminjam.pop();
                }
                
                daftarBuku[i].peminjam = "-"; // Reset nama peminjam menjadi tanda hubung kembali
                cout << "Buku berhasil dikembalikan. Ditambahkan ke riwayat tumpukan (Stack).\n";
            } else {
                cout << "Buku ini sudah tersedia di perpustakaan (tidak sedang dipinjam).\n";
            }
            return;
        }
    }
    cout << "Buku tidak ditemukan.\n";
}

// --- 8. FUNGSI URUTKAN BUKU (BUBBLE SORT) ---
void urutkanBuku() {
    int pilihan;
    cout << "\nUrutkan berdasarkan:\n1. Judul\n2. Tahun\nPilih: ";
    cin >> pilihan;

    for (int i = 0; i < jumlahBuku - 1; i++) {
        for (int j = 0; j < jumlahBuku - i - 1; j++) {
            bool tukar = false;
            if (pilihan == 1 && daftarBuku[j].judul > daftarBuku[j + 1].judul) {
                tukar = true;
            } else if (pilihan == 2 && daftarBuku[j].tahun > daftarBuku[j + 1].tahun) {
                tukar = true;
            }
            
            if (tukar) {
                Buku temp = daftarBuku[j];
                daftarBuku[j] = daftarBuku[j + 1];
                daftarBuku[j + 1] = temp;
            }
        }
    }
    cout << "Buku berhasil diurutkan!\n";
    tampilkanBuku();
}

// --- 9. FUNGSI STATISTIK BUKU ---
void statistikBuku() {
    int dipinjam = 0, tersedia = 0;
    for (int i = 0; i < jumlahBuku; i++) {
        if (daftarBuku[i].tersedia) tersedia++;
        else dipinjam++;
    }
    
    cout << "\n--- Statistik Perpustakaan ---\n";
    cout << "Jumlah Total Buku : " << jumlahBuku << "\n";
    cout << "Jumlah Dipinjam   : " << dipinjam << "\n";
    cout << "Jumlah Tersedia   : " << tersedia << "\n";
    
    if(!riwayatPengembalian.empty()) {
        cout << "Buku terakhir dikembalikan (Stack) : " << riwayatPengembalian.top() << "\n";
    }
}

// --- FUNGSI UTAMA ---
int main() {
    // Data Dummy awal
    daftarBuku[jumlahBuku++] = {"B001", "AI", "Ahmad", 2024, true, "-"};
    daftarBuku[jumlahBuku++] = {"B002", "C++", "Budi", 2023, true, "-"};

    int pilihan;
    do {
        cout << "\n===== SISTEM PERPUSTAKAAN =====\n";
        cout << "1. Tambah Buku\n";
        cout << "2. Tampilkan Buku\n";
        cout << "3. Cari Buku\n";
        cout << "4. Ubah Data Buku\n";
        cout << "5. Hapus Buku\n";
        cout << "6. Pinjam Buku\n";
        cout << "7. Kembalikan Buku\n";
        cout << "8. Urutkan Judul Buku\n";
        cout << "9. Statistik Buku\n";
        cout << "0. Keluar\n";
        cout << "Pilih menu: ";
        cin >> pilihan;

        switch (pilihan) {
            case 1: tambahBuku(); break;
            case 2: tampilkanBuku(); break;
            case 3: cariBuku(); break;
            case 4: ubahBuku(); break;
            case 5: hapusBuku(); break;
            case 6: pinjamBuku(); break;
            case 7: kembalikanBuku(); break;
            case 8: urutkanBuku(); break;
            case 9: statistikBuku(); break;
            case 0: cout << "Keluar dari program. Terima kasih!\n"; break;
            default: cout << "Pilihan tidak valid!\n";
        }
    } while (pilihan != 0);

    return 0;
}