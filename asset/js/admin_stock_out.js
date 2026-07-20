let isBusy = false;
const recent = [];
let scannerMode = "quagga";
let quaggaInitialized = false;
let currentFacing = "environment";
let isTorchOn = false;
let track = null;
let nativeStream = null;
window.scanCooldown = false;
window.lastScan = "";

let _selectedPaymentId = null;
let _selectedPaymentLabel = null;

window.hargaJualDefault = 0;
window.modalBarang = 0;
window.barangData = null;

const swalConfig = {
  background: "#FFFFFF",
  color: "#1E293B",
  confirmButtonColor: "#1E4A7A",
  cancelButtonColor: "#F1F5F9",
  customClass: { popup: "rounded-4", cancelButton: "text-dark fw-bold" },
};

const beep = document.getElementById("beep");

const paymentMethods = [
  { id: "Cash", label: "Cash", icon: "fa-money-bill-wave", color: "#10B981" },
  { id: "Transfer", label: "Transfer", icon: "fa-university", color: "#3B82F6" },
  { id: "Kredivo", label: "Kredivo", icon: "fa-credit-card", color: "#EF4444" },
  { id: "Akulaku", label: "Akulaku", icon: "fa-wallet", color: "#F59E0B" },
  { id: "Shopee PayLater", label: "Shopee PayLater", icon: "fa-shopping-bag", color: "#F97316" },
];

// ===== FUNGSI FORMAT RUPIAH =====
function formatRupiah(angka) {
  if (!angka) return "";
  const number_string = angka.toString().replace(/[^,\d]/g, "");
  const split = number_string.split(",");
  const sisa = split[0].length % 3;
  let rupiah = split[0].substr(0, sisa);
  const ribuan = split[0].substr(sisa).match(/\d{3}/gi);
  if (ribuan) {
    const separator = sisa ? "." : "";
    rupiah += separator + ribuan.join(".");
  }
  return rupiah;
}

function parseRupiah(value) {
  if (!value) return 0;
  return parseInt(value.replace(/[^0-9]/g, "")) || 0;
}

// ===== FUNGSI PREVIEW BARANG =====
let searchTimeout;
function previewBarang(imei) {
  const previewContainer = document.getElementById("preview-container");
  const btnProses = document.getElementById("btn-proses");

  if (!imei || imei.length < 5) {
    previewContainer.innerHTML = "";
    btnProses.disabled = true;
    window.barangData = null;
    return;
  }

  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(async () => {
    try {
      const resp = await fetch("admin_stock_out.php?action=get_barang_detail&imei=" + encodeURIComponent(imei));
      const result = await resp.json();

      if (result.success && result.data) {
        const data = result.data;
        window.barangData = data;
        window.hargaJualDefault = data.harga_jual || 0;

        const statusClass = data.status === "READY" ? "ready" : "sold";
        const statusText = data.status === "READY" ? "READY" : "SOLD";

        previewContainer.innerHTML = `
                    <div class="preview-card">
                        <div class="preview-header">
                            <div class="preview-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="preview-title">
                                <div class="preview-title-label">Detail Barang</div>
                                <div class="preview-title-value">${data.nama} ${data.tipe}</div>
                            </div>
                        </div>
                        <div class="preview-grid">
                            <div class="preview-item">
                                <div class="preview-item-label">Warna</div>
                                <div class="preview-item-value">${data.warna || "-"}</div>
                            </div>
                            <div class="preview-item">
                                <div class="preview-item-label">Internal</div>
                                <div class="preview-item-value">${data.internal || "-"}</div>
                            </div>
                        </div>
                        <div class="preview-price">
                            <div class="preview-price-label">Harga Jual</div>
                            <div class="preview-price-value">Rp ${formatRupiah(data.harga_jual)}</div>
                        </div>
                        <div style="text-align: center;">
                            <span class="preview-status ${statusClass}">${statusText}</span>
                        </div>
                    </div>
                `;

        btnProses.disabled = data.status !== "READY";
      } else {
        previewContainer.innerHTML = "";
        btnProses.disabled = true;
        window.barangData = null;
      }
    } catch (e) {
      console.error("Error fetching barang detail:", e);
      previewContainer.innerHTML = "";
      btnProses.disabled = true;
      window.barangData = null;
    }
  }, 500);
}

function buildPaymentHTML() {
  const btns = paymentMethods
    .map((m) => {
      const safeId = m.id.replace(/\s/g, "_");
      return `<button type="button" class="pay-btn" id="pbtn-${safeId}"
            onclick="pilihPayment('${m.id}','${m.label}')">
            <i class="fas ${m.icon}" style="color:${m.color}"></i>
            ${m.label}
        </button>`;
    })
    .join("");
  return `
        <p style="color:#64748B;font-size:0.85rem;margin-bottom:14px;font-weight:500;">
            Pilih metode pembayaran customer
        </p>
        <div class="pay-grid">${btns}</div>
        <div class="pay-confirm-badge" id="pay-confirm-badge">
            <i class="fas fa-check-circle"></i>
            <span id="pay-confirm-label"></span>
        </div>
    `;
}

function pilihPayment(id, label) {
  _selectedPaymentId = id;
  _selectedPaymentLabel = label;
  document.querySelectorAll(".pay-btn").forEach((b) => b.classList.remove("active"));
  const btn = document.getElementById("pbtn-" + id.replace(/\s/g, "_"));
  if (btn) btn.classList.add("active");
  const badge = document.getElementById("pay-confirm-badge");
  const lbl = document.getElementById("pay-confirm-label");
  if (badge && lbl) {
    lbl.textContent = label + " dipilih";
    badge.style.display = "flex";
  }
  const confirmBtn = Swal.getConfirmButton();
  if (confirmBtn) {
    confirmBtn.disabled = false;
    confirmBtn.style.opacity = "1";
    confirmBtn.style.cursor = "pointer";
  }
  updateSimulasiCicilan();
}

function updateSimulasiCicilan() {
  const metode = _selectedPaymentId;
  const isCicilan = ["Kredivo", "Akulaku", "Shopee PayLater"].includes(metode);
  const dpWrapper = document.getElementById("dp-wrapper");
  const simulasiEl = document.getElementById("simulasi-cicilan");
  if (!dpWrapper || !simulasiEl) return;
  if (isCicilan) {
    dpWrapper.style.display = "block";
    const hargaInput = document.getElementById("harga-jual-input");
    const dpInput = document.getElementById("dp-input");
    const harga = parseRupiah(hargaInput?.value);
    const dp = parseRupiah(dpInput?.value);
    const sisa = harga - dp;
    document.getElementById("sisa-cicilan").textContent = "Rp " + formatRupiah(sisa > 0 ? sisa : 0);
    simulasiEl.style.display = "block";
  } else {
    dpWrapper.style.display = "none";
    simulasiEl.style.display = "none";
  }
}

function clearInput() {
  document.getElementById("imei_input").value = "";
  document.getElementById("imei_input").focus();
  document.getElementById("preview-container").innerHTML = "";
  document.getElementById("btn-proses").disabled = true;
  window.barangData = null;
}

function addActivity(nama, metode, catatan, time) {
  recent.unshift({ nama, metode, catatan, time });
  renderActivity();
}

function renderActivity() {
  const el = document.getElementById("recentScans");
  if (!el) return;
  if (recent.length === 0) {
    el.innerHTML = `<div class="text-center text-muted py-4 small"><i class="fas fa-receipt fs-2 mb-2" style="opacity: 0.5;"></i><br>Belum ada aktivitas</div>`;
    return;
  }
  el.innerHTML = recent
    .slice(0, 5)
    .map(
      (r) => `
        <div class="activity-item">
            <div>
                <div class="act-name">${r.nama}</div>
                <div class="act-meta">${r.time} &nbsp;·&nbsp; ${r.metode}${r.catatan ? " · " + r.catatan : ""}</div>
            </div>
        </div>
    `,
    )
    .join("");
}

async function tanyaKeterangan(imei) {
  if (!imei || imei.length < 5) {
    return Swal.fire({ ...swalConfig, icon: "warning", title: "IMEI tidak valid", text: "Minimal 5 karakter." });
  }
  if (isBusy) return;

  if (!window.barangData) {
    return Swal.fire({ ...swalConfig, icon: "warning", title: "Data tidak ditemukan", text: "Silakan tunggu data barang dimuat." });
  }

  if (window.barangData.status !== "READY") {
    return Swal.fire({ ...swalConfig, icon: "error", title: "Barang tidak tersedia", text: "Status barang sudah SOLD." });
  }

  _selectedPaymentId = null;
  _selectedPaymentLabel = null;

  // Step 1: Pilih metode
  const step1 = await Swal.fire({
    ...swalConfig,
    title: '<span style="font-size:1.1rem;font-weight:800;color:var(--text-main);">Metode Pembayaran</span>',
    html: buildPaymentHTML(),
    showCancelButton: true,
    confirmButtonText: 'Lanjut <i class="fas fa-arrow-right ms-1"></i>',
    cancelButtonText: "Batal",
    didOpen: () => {
      const btn = Swal.getConfirmButton();
      btn.disabled = true;
      btn.style.opacity = "0.4";
      btn.style.cursor = "not-allowed";
    },
    preConfirm: () => {
      if (!_selectedPaymentId) {
        Swal.showValidationMessage("Pilih metode pembayaran terlebih dahulu");
        return false;
      }
      return true;
    },
  });

  if (!step1.isConfirmed || !_selectedPaymentId) return;

  const metodeId = _selectedPaymentId;
  const metodeLabel = _selectedPaymentLabel;
  const isCicilan = ["Kredivo", "Akulaku", "Shopee PayLater"].includes(metodeId);

  // Step 2: Detail transaksi dengan UI yang lebih bagus
  const step2 = await Swal.fire({
    ...swalConfig,
    title: '<span style="font-size:1.1rem;font-weight:800;color:var(--text-main);">Detail Transaksi</span>',
    width: "500px",
    html: `
            <style>
                .swal-input-group { margin-bottom: 14px; text-align: left; }
                .swal-input-group label {
                    display: flex; align-items: center; gap: 6px;
                    color: #64748B; font-size: 0.8rem; margin-bottom: 6px; font-weight: 600;
                }
                .swal-input-group label i { font-size: 0.85rem; color: var(--primary-dongker); }
                .swal-input-wrapper {
                    position: relative; display: flex; align-items: center;
                    background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px;
                    transition: all 0.2s;
                }
                .swal-input-wrapper:focus-within {
                    border-color: var(--primary-dongker); background: white;
                    box-shadow: 0 0 0 3px rgba(30, 74, 122, 0.1);
                }
                .swal-input-prefix {
                    padding: 0 10px; color: #64748B; font-weight: 700; font-size: 0.9rem;
                    border-right: 1px solid #E2E8F0; background: #F1F5F9;
                    height: 44px; display: flex; align-items: center;
                    border-radius: 12px 0 0 12px;
                }
                .swal-input-field {
                    flex: 1; border: none; background: transparent; padding: 12px 14px;
                    font-size: 0.95rem; font-weight: 700; color: #1E293B; outline: none;
                    font-family: 'Plus Jakarta Sans', sans-serif;
                }
                .swal-input-field::placeholder { color: #94A3B8; font-weight: 500; }
                .row-mobile { display: flex; flex-wrap: wrap; margin: 0 -6px; }
                .col-mobile { flex: 1 0 50%; padding: 0 6px; }
                .info-box {
                    background: linear-gradient(135deg, #EEF4FA 0%, #F8FAFC 100%);
                    border: 1px solid #1E4A7A; border-radius: 12px;
                    padding: 12px 16px; margin-bottom: 16px;
                    display: flex; align-items: center; gap: 10px;
                }
                .info-box i { font-size: 1.2rem; color: #1E4A7A; }
                .info-box-content { flex: 1; }
                .info-box-label { font-size: 0.7rem; color: #64748B; font-weight: 600; text-transform: uppercase; }
                .info-box-value { font-size: 0.9rem; color: #1E4A7A; font-weight: 800; }
                .simulasi-box {
                    background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px;
                    padding: 12px 14px; margin-top: 12px;
                }
                .simulasi-row {
                    display: flex; justify-content: space-between; align-items: center;
                    font-size: 0.8rem; margin-bottom: 4px;
                }
                .simulasi-row:last-child { margin-bottom: 0; padding-top: 6px; border-top: 1px dashed #E2E8F0; }
                .simulasi-label { color: #64748B; font-weight: 600; }
                .simulasi-value { color: #1E4A7A; font-weight: 800; }
                @media (max-width: 480px) {
                    .col-mobile { flex: 1 0 100%; margin-bottom: 10px; }
                }
            </style>

            <div class="info-box">
                <i class="fas fa-tag"></i>
                <div class="info-box-content">
                    <div class="info-box-label">Metode Pembayaran</div>
                    <div class="info-box-value">${metodeLabel}</div>
                </div>
            </div>

            <div class="swal-input-group">
                <label><i class="fas fa-money-bill-wave"></i> Harga Jual (Rp)</label>
                <div class="swal-input-wrapper">
                    <div class="swal-input-prefix">Rp</div>
                    <input id="harga-jual-input" class="swal-input-field" type="text" 
                           value="${formatRupiah(window.hargaJualDefault)}" 
                           placeholder="0" inputmode="numeric">
                </div>
            </div>

            <div id="dp-wrapper" style="display:none;">
                <div class="swal-input-group">
                    <label><i class="fas fa-wallet"></i> DP / Uang Muka (Rp)</label>
                    <div class="swal-input-wrapper">
                        <div class="swal-input-prefix">Rp</div>
                        <input id="dp-input" class="swal-input-field" type="text" 
                               placeholder="0" inputmode="numeric">
                    </div>
                </div>
            </div>

            <div class="row-mobile" style="margin-top:4px;">
                <div class="col-mobile">
                    <div class="swal-input-group">
                        <label><i class="fas fa-user"></i> Nama Customer</label>
                        <div class="swal-input-wrapper">
                            <input id="nama-customer" class="swal-input-field" placeholder="Nama lengkap">
                        </div>
                    </div>
                </div>
                <div class="col-mobile">
                    <div class="swal-input-group">
                        <label><i class="fas fa-phone"></i> No. WhatsApp</label>
                        <div class="swal-input-wrapper">
                            <input id="no-hp-customer" class="swal-input-field" placeholder="0812xxx" inputmode="tel">
                        </div>
                    </div>
                </div>
            </div>

            <div class="swal-input-group">
                <label><i class="fas fa-sticky-note"></i> Catatan Tambahan</label>
                <div class="swal-input-wrapper">
                    <input id="catatan-input" class="swal-input-field" placeholder="Opsional (Kondisi/Kelengkapan)">
                </div>
            </div>

            <div id="simulasi-cicilan" style="display:none;">
                <div class="simulasi-box">
                    <div class="simulasi-row">
                        <span class="simulasi-label">Harga Barang</span>
                        <span class="simulasi-value" id="sim-harga">Rp 0</span>
                    </div>
                    <div class="simulasi-row">
                        <span class="simulasi-label">DP Dibayar</span>
                        <span class="simulasi-value" id="sim-dp">Rp 0</span>
                    </div>
                    <div class="simulasi-row">
                        <span class="simulasi-label">Sisa dibiayai ${metodeLabel}</span>
                        <span class="simulasi-value" id="sisa-cicilan" style="color: #10B981; font-size: 0.95rem;">Rp 0</span>
                    </div>
                </div>
            </div>
        `,
    showCancelButton: true,
    confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Proses',
    cancelButtonText: '<i class="fas fa-arrow-left me-1"></i> Kembali',
    didOpen: () => {
      const hargaInput = document.getElementById("harga-jual-input");
      const dpInput = document.getElementById("dp-input");

      if (hargaInput) {
        hargaInput.addEventListener("input", function (e) {
          const cursorPos = this.selectionStart;
          const oldLength = this.value.length;
          this.value = formatRupiah(parseRupiah(this.value));
          const newLength = this.value.length;
          this.setSelectionRange(cursorPos + (newLength - oldLength), cursorPos + (newLength - oldLength));
          updateSimulasiCicilan();
        });
      }

      if (dpInput) {
        dpInput.addEventListener("input", function (e) {
          const cursorPos = this.selectionStart;
          const oldLength = this.value.length;
          this.value = formatRupiah(parseRupiah(this.value));
          const newLength = this.value.length;
          this.setSelectionRange(cursorPos + (newLength - oldLength), cursorPos + (newLength - oldLength));
          updateSimulasiCicilan();
        });
      }

      updateSimulasiCicilan();
    },
    preConfirm: () => {
      const harga = parseRupiah(document.getElementById("harga-jual-input").value);
      const nama = document.getElementById("nama-customer").value.trim();
      const noHp = document.getElementById("no-hp-customer").value.trim();
      const catatan = document.getElementById("catatan-input").value.trim();
      const dp = parseRupiah(document.getElementById("dp-input")?.value);

      if (!harga || harga <= 0) {
        Swal.showValidationMessage("Harga jual harus diisi");
        return false;
      }
      if (!nama) {
        Swal.showValidationMessage("Nama customer wajib diisi");
        return false;
      }
      if (["Kredivo", "Akulaku", "Shopee PayLater"].includes(_selectedPaymentId)) {
        if (!dp || dp < 0) {
          Swal.showValidationMessage("DP wajib diisi untuk cicilan");
          return false;
        }
        if (dp > harga) {
          Swal.showValidationMessage("DP tidak boleh melebihi harga jual");
          return false;
        }
      }
      return { harga: harga.toString(), nama, noHp, catatan, dp: dp.toString() };
    },
  });

  if (step2.isDismissed && step2.dismiss === Swal.DismissReason.cancel) {
    return tanyaKeterangan(imei);
  }
  if (!step2.isConfirmed) return;

  const { harga, nama, noHp, catatan, dp } = step2.value;
  kirimData(imei, metodeLabel, harga, nama, noHp, catatan, dp);
}

function kirimData(imei, metodeLabel, hargaFinal, namaCustomer, noHp, catatan, dp) {
  isBusy = true;
  Swal.fire({ ...swalConfig, title: "Memproses...", allowOutsideClick: false, didOpen: () => Swal.showLoading() });

  const fd = new FormData();
  fd.append("action", "scan_out");
  fd.append("imei", imei);
  fd.append("metode", metodeLabel);
  fd.append("harga_jual", hargaFinal);
  fd.append("nama_customer", namaCustomer);
  fd.append("no_hp", noHp);
  fd.append("keterangan", catatan);
  fd.append("dp", dp);

  fetch("../api/ajax_stock_out.php", { method: "POST", body: fd })
    .then((r) => r.json())
    .then((res) => {
      if (res.status === "success") {
        Swal.fire({
          ...swalConfig,
          icon: "success",
          title: "Berhasil",
          html: `<b style="color:var(--primary-dongker);font-size:1.1rem;">${res.nama}</b><br><small style="color:var(--text-muted)">Transaksi selesai diproses</small>`,
        });
        const now = new Date();
        addActivity(res.nama, metodeLabel, catatan, now.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" }));
        clearInput();
      } else {
        Swal.fire({ ...swalConfig, icon: "error", title: "Gagal", text: res.msg });
      }
    })
    .catch(() => Swal.fire({ ...swalConfig, icon: "error", title: "Koneksi Gagal", text: "Periksa koneksi internet anda." }))
    .finally(() => {
      isBusy = false;
    });
}

// ===== POPUP MENU LAINNYA =====
const moreBtn = document.getElementById("moreMenuBtn");
const popup = document.getElementById("moreMenuPopup");
const overlay = document.getElementById("popupOverlay");

if (moreBtn) {
  moreBtn.addEventListener("click", function (e) {
    e.stopPropagation();
    popup.classList.toggle("show");
    overlay.classList.toggle("show");
  });
}
if (overlay) {
  overlay.addEventListener("click", function () {
    popup.classList.remove("show");
    overlay.classList.remove("show");
  });
}
document.addEventListener("click", function (e) {
  if (popup && !popup.contains(e.target) && e.target !== moreBtn && !moreBtn.contains(e.target)) {
    popup.classList.remove("show");
    overlay.classList.remove("show");
  }
});

// ===== SCANNER FUNCTIONS =====
function canUseBarcodeDetector() {
  return "BarcodeDetector" in window && "getUserMedia" in navigator.mediaDevices;
}
async function updateTorchAvailability() {
  let videoTrack = null;
  if (scannerMode === "native" && nativeStream) {
    videoTrack = nativeStream.getVideoTracks()[0];
  } else if (scannerMode === "quagga" && quaggaInitialized) {
    const stream = Quagga.CameraAccess.getActiveStream();
    if (stream) videoTrack = stream.getVideoTracks()[0];
  }
  const torchBtn = document.getElementById("torchBtn");
  if (videoTrack) {
    track = videoTrack;
    torchBtn.style.display = track.getCapabilities().torch ? "flex" : "none";
  } else {
    torchBtn.style.display = "none";
  }
}

function toggleTorch() {
  if (!track) return;
  isTorchOn = !isTorchOn;
  track.applyConstraints({ advanced: [{ torch: isTorchOn }] }).catch(() => Swal.fire({ ...swalConfig, icon: "error", title: "Gagal menyalakan senter" }));
  const icon = document.querySelector("#torchBtn i");
  const btn = document.getElementById("torchBtn");
  if (isTorchOn) {
    icon.classList.replace("fa-bolt", "fa-bolt-slash");
    btn.style.background = "rgba(255,255,255,0.5)";
  } else {
    icon.classList.replace("fa-bolt-slash", "fa-bolt");
    btn.style.background = "rgba(255,255,255,0.2)";
  }
}

async function startNativeScanner(facing) {
  const readerEl = document.getElementById("reader");
  readerEl.innerHTML = "";
  const video = document.createElement("video");
  video.setAttribute("autoplay", "");
  video.setAttribute("playsinline", "");
  video.setAttribute("muted", "");
  video.style.cssText = "width:100%;height:100%;object-fit:cover;";
  readerEl.appendChild(video);
  nativeStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
  video.srcObject = nativeStream;
  await video.play();
  const barcodeDetector = new BarcodeDetector({ formats: ["code_128", "ean_13", "ean_8", "code_39", "upc_a"] });
  const detectLoop = async () => {
    if (!nativeStream || !video.readyState) return;
    try {
      const barcodes = await barcodeDetector.detect(video);
      if (barcodes.length > 0 && !window.scanCooldown) {
        const code = barcodes[0].rawValue;
        if (code !== window.lastScan) {
          handleSuccessfulScan(code);
          return;
        }
      }
    } catch (err) {}
    if (nativeStream) requestAnimationFrame(detectLoop);
  };
  detectLoop();
}

function stopNativeScanner() {
  if (nativeStream) {
    nativeStream.getTracks().forEach((t) => t.stop());
    nativeStream = null;
  }
  document.getElementById("reader").innerHTML = "";
}

function startQuagga(facing) {
  if (quaggaInitialized) {
    Quagga.stop();
    Quagga.offDetected();
    quaggaInitialized = false;
  }
  Quagga.init(
    {
      inputStream: {
        name: "Live",
        type: "LiveStream",
        target: document.querySelector("#reader"),
        constraints: { facingMode: facing, width: { min: 640, ideal: 1280 }, height: { min: 480, ideal: 720 } },
        area: { top: "25%", right: "15%", left: "15%", bottom: "30%" },
      },
      decoder: { readers: ["code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader", "upc_reader"] },
      locator: { patchSize: "medium", halfSample: false },
      numOfWorkers: navigator.hardwareConcurrency || 4,
      frequency: 10,
      locate: true,
    },
    (err) => {
      if (err) {
        Swal.fire({ ...swalConfig, icon: "error", title: "Kamera tidak dapat diakses" });
        closeScanner();
        return;
      }
      quaggaInitialized = true;
      Quagga.start();
    },
  );
  Quagga.offDetected();
  Quagga.onDetected((result) => {
    const code = result.codeResult.code;
    if (!window.scanCooldown && code !== window.lastScan) handleSuccessfulScan(code);
  });
}

function handleSuccessfulScan(code) {
  window.scanCooldown = true;
  window.lastScan = code;
  beep.play().catch(() => {});
  document.getElementById("scanner-container").classList.add("scanner-success");
  setTimeout(() => {
    document.getElementById("imei_input").value = code;
    previewBarang(code);
    closeScanner();
    setTimeout(() => {
      window.scanCooldown = false;
      window.lastScan = "";
    }, 2000);
  }, 400);
}

async function startScanner(facing) {
  if (scannerMode === "native" && nativeStream) stopNativeScanner();
  else if (scannerMode === "quagga" && quaggaInitialized) {
    Quagga.stop();
    Quagga.offDetected();
    quaggaInitialized = false;
  }
  if (canUseBarcodeDetector()) {
    try {
      await startNativeScanner(facing);
      scannerMode = "native";
    } catch (e) {
      startQuagga(facing);
      scannerMode = "quagga";
    }
  } else {
    startQuagga(facing);
    scannerMode = "quagga";
  }
  await updateTorchAvailability();
}

function openScanner() {
  document.getElementById("scanner-container").style.display = "block";
  document.getElementById("scanner-container").classList.remove("scanner-success");
  window.scanCooldown = false;
  window.lastScan = "";
  isTorchOn = false;
  const torchIcon = document.querySelector("#torchBtn i");
  if (torchIcon) {
    torchIcon.classList.remove("fa-bolt-slash");
    torchIcon.classList.add("fa-bolt");
    document.getElementById("torchBtn").style.background = "rgba(255,255,255,0.2)";
  }
  startScanner(currentFacing);
}

function closeScanner() {
  document.getElementById("scanner-container").style.display = "none";
  if (track && isTorchOn) track.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {});
  if (scannerMode === "native") stopNativeScanner();
  else if (scannerMode === "quagga" && quaggaInitialized) {
    Quagga.stop();
    Quagga.offDetected();
    quaggaInitialized = false;
  }
  track = null;
  isTorchOn = false;
}

function switchCamera() {
  currentFacing = currentFacing === "environment" ? "user" : "environment";
  if (track && isTorchOn) {
    track.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {});
    isTorchOn = false;
  }
  startScanner(currentFacing);
}

// Event listener untuk input IMEI
document.getElementById("imei_input").addEventListener("input", function (e) {
  previewBarang(this.value);
});

document.getElementById("imei_input").addEventListener("keypress", (e) => {
  if (e.key === "Enter") {
    e.preventDefault();
    if (!document.getElementById("btn-proses").disabled) {
      tanyaKeterangan(e.target.value);
    }
  }
});
