<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Happy Cell · Premium Mobile Store Payakumbuh</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Outfit:wght@400;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-outfit { font-family: 'Outfit', sans-serif; }
        
        .glass-nav {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .animate-reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .animate-reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .bg-grid {
            background-image: radial-gradient(#e5e7eb 1px, transparent 1px);
            background-size: 40px 40px;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        .float-anim { animation: float 5s ease-in-out infinite; }
    </style>
</head>
<body class="bg-white text-gray-900 overflow-x-hidden" x-data="{ mobileMenu: false }">

    <nav class="glass-nav fixed top-0 w-full z-[100] border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <a href="#" class="flex items-center gap-2 text-2xl font-bold tracking-tighter hover:opacity-70 transition">
                <div class="w-10 h-10 bg-black text-white rounded-xl flex items-center justify-center">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>
                <span>Happy Cell.</span>
            </a>

            <div class="hidden md:flex items-center gap-10 text-sm font-medium text-gray-600">
                <a href="#home" class="hover:text-black transition">Beranda</a>
                <a href="#products" class="hover:text-black transition">Koleksi</a>
                <a href="#services" class="hover:text-black transition">Layanan</a>
                <a href="#location" class="hover:text-black transition">Lokasi</a>
                <a href="https://wa.me/6282170327059" class="bg-black text-white px-6 py-2.5 rounded-full hover:bg-gray-800 transition">Konsultasi Gratis</a>
            </div>

            <button @click="mobileMenu = !mobileMenu" class="md:hidden text-2xl">
                <i :class="mobileMenu ? 'fa-solid fa-xmark' : 'fa-solid fa-bars-staggered'"></i>
            </button>
        </div>

        <div x-show="mobileMenu" x-transition.opacity class="fixed inset-0 bg-white z-[99] md:hidden pt-24 px-8">
            <div class="flex flex-col gap-8 text-2xl font-bold">
                <a @click="mobileMenu = false" href="#home">Beranda</a>
                <a @click="mobileMenu = false" href="#products">Koleksi</a>
                <a @click="mobileMenu = false" href="#services">Layanan</a>
                <a @click="mobileMenu = false" href="#location">Lokasi</a>
                <a href="https://wa.me/6282170327059" class="text-green-600">WhatsApp <i class="fa-brands fa-whatsapp"></i></a>
            </div>
        </div>
    </nav>

    <main>
        <section id="home" class="relative pt-32 pb-20 md:pt-48 md:pb-32 bg-grid">
            <div class="max-w-7xl mx-auto px-6 grid md:grid-cols-2 gap-16 items-center">
                <div class="animate-reveal visible">
                    <span class="inline-block px-4 py-1.5 bg-gray-100 rounded-full text-xs font-bold tracking-widest uppercase mb-6">
                        ✨ Gadget Bergaransi di Payakumbuh
                    </span>
                    <h1 class="text-6xl md:text-8xl font-bold leading-[0.9] tracking-tighter mb-8">
                        Upgrade ke <br><span class="text-gray-400">Flagship</span> Baru.
                    </h1>
                    <p class="text-lg text-gray-500 max-w-md mb-10 leading-relaxed">
                        Kami menyediakan iPhone & Android original dengan jaminan IMEI aman seumur hidup. Bisa tukar tambah & COD khusus area Payakumbuh.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a href="#products" class="bg-black text-white px-8 py-4 rounded-full text-lg font-semibold hover:scale-105 transition shadow-2xl">
                            Lihat Katalog
                        </a>
                        <a href="https://wa.me/6282170327059?text=Halo%20Happy%20Cell,%20saya%20mau%20tanya%20stok%20HP" class="border border-black px-8 py-4 rounded-full text-lg font-semibold hover:bg-black hover:text-white transition">
                            Cek Stok Sekarang
                        </a>
                    </div>
                </div>
                <div class="relative animate-reveal visible delay-2">
                    <div class="float-anim bg-gray-50 rounded-[3rem] p-4 border border-gray-100 shadow-inner">
                        <img src="https://images.unsplash.com/photo-1696446701796-da61225697cc?q=80&w=800" alt="iPhone 15" class="rounded-[2.5rem] shadow-2xl">
                    </div>
                    <div class="absolute -bottom-6 -left-6 bg-white p-6 rounded-3xl shadow-xl border border-gray-100 flex items-center gap-4">
                        <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xl">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <div>
                            <p class="font-bold">Bisa COD</p>
                            <p class="text-xs text-gray-400">Payakumbuh & sekitarnya</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="services" class="py-20 bg-black text-white">
            <div class="max-w-7xl mx-auto px-6 grid md:grid-cols-3 gap-12">
                <div class="animate-reveal">
                    <i class="fa-solid fa-shield-halved text-4xl mb-6 text-gray-400"></i>
                    <h3 class="text-xl font-bold mb-4">Garansi Resmi</h3>
                    <p class="text-gray-400 leading-relaxed">Semua unit baru bergaransi resmi Indonesia. Untuk unit second, kami beri garansi toko yang jelas.</p>
                </div>
                <div class="animate-reveal delay-1">
                    <i class="fa-solid fa-rotate text-4xl mb-6 text-gray-400"></i>
                    <h3 class="text-xl font-bold mb-4">Tukar Tambah</h3>
                    <p class="text-gray-400 leading-relaxed">Punya HP lama? Bawa ke toko dan tukar dengan yang baru. Kami hargai HP lama Anda dengan harga kompetitif.</p>
                </div>
                <div class="animate-reveal delay-2">
                    <i class="fa-solid fa-fingerprint text-4xl mb-6 text-gray-400"></i>
                    <h3 class="text-xl font-bold mb-4">IMEI Terdaftar</h3>
                    <p class="text-gray-400 leading-relaxed">Lupakan sinyal hilang. Unit kami terjamin terdaftar di database Kemenperin/Bea Cukai seumur hidup.</p>
                </div>
            </div>
        </section>

        <section id="products" class="py-24 max-w-7xl mx-auto px-6">
            <div class="flex justify-between items-end mb-16 animate-reveal">
                <div>
                    <h2 class="text-4xl font-bold mb-2 tracking-tight">Koleksi Terpopuler</h2>
                    <p class="text-gray-500">Pilihan terbaik untuk performa maksimal.</p>
                </div>
                <a href="https://instagram.com/happycellular__/" class="text-black font-semibold border-b-2 border-black pb-1">Lihat Semua</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <div class="product-card group animate-reveal">
                    <div class="bg-gray-50 rounded-[2rem] overflow-hidden mb-6 aspect-square flex items-center justify-center p-12 transition-all">
                        <img src="https://images.unsplash.com/photo-1678911820864-e2c567c655d7?q=80&w=600" alt="iPhone 15 Pro" class="product-image transition-transform duration-500 object-contain">
                    </div>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Apple</p>
                            <h4 class="text-xl font-bold">iPhone 15 Pro Series</h4>
                        </div>
                        <span class="bg-gray-100 px-3 py-1 rounded-lg font-bold text-sm">Baru</span>
                    </div>
                    <a href="https://wa.me/6282170327059" class="mt-6 block w-full text-center py-4 rounded-xl border border-gray-200 font-semibold group-hover:bg-black group-hover:text-white transition">Tanya Harga</a>
                </div>

                <div class="product-card group animate-reveal delay-1">
                    <div class="bg-gray-50 rounded-[2rem] overflow-hidden mb-6 aspect-square flex items-center justify-center p-12 transition-all">
                        <img src="https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?q=80&w=600" alt="S24 Ultra" class="product-image transition-transform duration-500 object-contain">
                    </div>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Samsung</p>
                            <h4 class="text-xl font-bold">Galaxy S24 Ultra</h4>
                        </div>
                        <span class="bg-black text-white px-3 py-1 rounded-lg font-bold text-sm">Best Seller</span>
                    </div>
                    <a href="https://wa.me/6282170327059" class="mt-6 block w-full text-center py-4 rounded-xl border border-gray-200 font-semibold group-hover:bg-black group-hover:text-white transition">Tanya Harga</a>
                </div>

                <div class="product-card group animate-reveal delay-2">
                    <div class="bg-gray-50 rounded-[2rem] overflow-hidden mb-6 aspect-square flex items-center justify-center p-12 transition-all">
                        <img src="https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?q=80&w=600" alt="iPhone 13" class="product-image transition-transform duration-500 object-contain">
                    </div>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Second Like New</p>
                            <h4 class="text-xl font-bold">iPhone 13 Series</h4>
                        </div>
                        <span class="bg-blue-50 text-blue-600 px-3 py-1 rounded-lg font-bold text-sm">Mulus</span>
                    </div>
                    <a href="https://wa.me/6282170327059" class="mt-6 block w-full text-center py-4 rounded-xl border border-gray-200 font-semibold group-hover:bg-black group-hover:text-white transition">Tanya Harga</a>
                </div>
            </div>
        </section>

        <section id="location" class="py-24 bg-gray-50">
            <div class="max-w-7xl mx-auto px-6">
                <div class="bg-white rounded-[3rem] overflow-hidden shadow-sm border border-gray-100 grid md:grid-cols-2">
                    <div class="p-12 md:p-20">
                        <h2 class="text-4xl font-bold mb-6">Mampir ke Toko.</h2>
                        <p class="text-gray-500 mb-10 leading-relaxed">
                            Nikmati pengalaman mencoba unit langsung di toko kami. Staf kami siap membantu setting HP baru Anda secara gratis.
                        </p>
                        
                        <div class="space-y-8">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 bg-black text-white rounded-full flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <div>
                                    <p class="font-bold">Alamat</p>
                                    <p class="text-gray-500 text-sm">Jl. Soekarno - Hatta, Padang Tinggi, Payakumbuh Barat, Kota Payakumbuh.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 bg-black text-white rounded-full flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-clock"></i>
                                </div>
                                <div>
                                    <p class="font-bold">Jam Operasional</p>
                                    <p class="text-gray-500 text-sm">Senin – Sabtu: 10:00 - 21:00<br>Minggu: 11:00 - 19:00</p>
                                </div>
                            </div>
                        </div>

                        <a href="https://goo.gl/maps/xyz" target="_blank" class="inline-block mt-12 bg-black text-white px-10 py-4 rounded-full font-bold shadow-xl hover:opacity-80 transition">
                            Petunjuk Jalan <i class="fa-solid fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                    <div class="h-80 md:h-auto bg-gray-200 relative">
                        <iframe class="absolute inset-0 w-full h-full grayscale contrast-125" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15959.043694002492!2d100.62121774218749!3d-0.229158300000002!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e2ab250918c502b%3A0x6b49045b467e2a9!2sPayakumbuh%20Kota!5e0!3m2!1sid!2sid!4v1715424000000!5m2!1sid!2sid" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-white pt-20 pb-10 border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-10 mb-20">
                <div>
                    <a href="#" class="text-3xl font-bold tracking-tighter">Happy Cell.</a>
                    <p class="text-gray-400 mt-2">Destinasi gadget premium nomor 1 di Payakumbuh.</p>
                </div>
                <div class="flex gap-8 text-2xl">
                    <a href="https://instagram.com/happycellular__/" class="hover:text-pink-600 transition"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="hover:text-black transition"><i class="fa-brands fa-tiktok"></i></a>
                    <a href="https://wa.me/6282170327059" class="hover:text-green-500 transition"><i class="fa-brands fa-whatsapp"></i></a>
                </div>
            </div>
            <div class="text-center text-gray-400 text-sm">
                <p>&copy; 2026 Happy Cell. Semua Hak Cipta Dilindungi.</p>
                <p class="mt-1 font-bold italic text-gray-300 tracking-widest">LUXURY IN EVERY PIXEL</p>
            </div>
        </div>
    </footer>

    <script>
        const observerOptions = {
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.animate-reveal').forEach((el) => {
            observer.observe(el);
        });
    </script>
</body>
</html>