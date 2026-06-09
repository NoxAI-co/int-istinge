<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagos en Línea | {{ $datosEmpresa['nombre'] }}</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <script src="https://checkout.epayco.co/checkout.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #05071a;
            color: #f0f4ff;
        }
        .bg-mesh {
            background: radial-gradient(ellipse at 20% 50%, rgba(25,51,136,0.3) 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 20%, rgba(0,198,255,0.15) 0%, transparent 50%),
                        radial-gradient(ellipse at 60% 80%, rgba(123,47,247,0.1) 0%, transparent 50%);
        }
        .space-grotesk {
            font-family: 'Space Grotesk', sans-serif;
        }
        [v-cloak] { display: none; }
    </style>
</head>
<body class="min-h-screen relative">
    
    <div class="fixed inset-0 pointer-events-none bg-mesh"></div>

    <nav class="fixed top-0 w-full z-50 bg-[#05071a]/80 backdrop-blur-2xl border-b border-white/[0.06]">
        <div class="max-w-6xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="/" class="flex items-center gap-3 group">
                <img src="{{ $datosEmpresa['logo'] }}" alt="{{ $datosEmpresa['nombre'] }}" class="h-9 object-contain transition-transform group-hover:scale-105" />
            </a>
            <div class="flex items-center gap-6">
                <a href="/" class="text-white/50 text-sm font-medium hover:text-white transition-colors">Inicio</a>
                <a href="#pago" class="text-[#00c6ff] text-sm font-semibold">Pagos en Línea</a>
            </div>
        </div>
    </nav>

    <section class="pt-28 pb-14 text-center px-4 relative z-10">
        <img src="{{ $datosEmpresa['logo'] }}" alt="{{ $datosEmpresa['nombre'] }}" class="h-20 mx-auto mb-6 drop-shadow-[0_0_40px_rgba(0,198,255,0.15)]" />
        <h1 class="text-4xl md:text-5xl font-extrabold mb-4 space-grotesk tracking-tight leading-tight">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-white via-white to-[#00c6ff]">Portal de Pagos</span>
            <br />
            <span class="text-white/90 text-3xl md:text-4xl font-bold">{{ $datosEmpresa['nombre'] }}</span>
        </h1>
        <p class="text-base text-white/50 mb-8 max-w-lg mx-auto leading-relaxed">
            Consulta y paga tus facturas de forma segura, rápida y al instante.
        </p>
        <div class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-5 py-2.5 rounded-full text-sm font-semibold">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
            Pago 100% Seguro
        </div>
        <div class="w-20 h-0.5 bg-gradient-to-r from-[#00c6ff] to-[#7b2ff7] mx-auto mt-10 rounded-full"></div>
    </section>

    <main class="max-w-4xl mx-auto px-4 pb-20 relative z-10" id="app" v-cloak>
        <div class="bg-white/[0.04] backdrop-blur-2xl border border-white/[0.08] rounded-3xl p-8 md:p-12 shadow-[0_32px_64px_rgba(0,0,0,0.5)]">
            
            <!-- Buscador -->
            <div class="max-w-md mx-auto" v-if="!selectedFactura">
                <label for="identificacion" class="block text-white/50 mb-2 text-sm font-medium">
                    Número de Identificación (Cédula / NIT)
                </label>
                <div class="flex gap-3">
                    <input id="identificacion" type="text" inputmode="numeric" v-model="identificacion" @keydown.enter="consultar" placeholder="Ej: 1234567890" class="flex-1 bg-white/[0.04] border border-white/10 rounded-xl px-5 py-3.5 text-white placeholder-white/20 focus:outline-none focus:border-[#00c6ff]/50 focus:ring-2 focus:ring-[#00c6ff]/20 transition-all text-base" />
                    <button @click="consultar" :disabled="loading" class="bg-gradient-to-br from-[#1d40b0] to-[#00b4e6] text-white px-6 py-3.5 rounded-xl font-semibold hover:shadow-[0_6px_24px_rgba(0,198,255,0.25)] hover:-translate-y-0.5 active:translate-y-0 transition-all disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                        <span v-if="loading" class="inline-block w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <span v-else>Consultar</span>
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="searched && facturas.length === 0 && !selectedFactura" class="mt-10 text-center text-white/40">
                <div class="text-5xl mb-4">📄</div>
                <p class="text-lg font-medium">No se encontraron facturas pendientes</p>
                <p class="text-sm mt-1">Verifica tu número de identificación e intenta nuevamente.</p>
            </div>

            <!-- Lista de Facturas -->
            <div v-if="facturas.length > 0 && !selectedFactura" class="mt-10">
                <h3 class="text-lg font-bold mb-6 text-center space-grotesk text-white/80">
                    Selecciona la factura que deseas pagar
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <button v-for="(f, idx) in facturas" :key="f.facturaId" @click="seleccionarFactura(f)" class="text-left bg-white/[0.03] border border-white/[0.08] rounded-2xl p-5 hover:bg-white/[0.07] hover:border-[#00c6ff]/30 hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,198,255,0.1)] transition-all duration-300 group">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-white/70 font-semibold text-sm">#@{{ f.factura }}</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 font-medium uppercase tracking-wider">Pendiente</span>
                        </div>
                        <div class="text-2xl font-bold text-[#00c6ff] group-hover:text-white transition-colors">
                            @{{ formatCurrency(f.price) }}
                        </div>
                        <div class="text-xs text-white/30 mt-2">Vence: @{{ f.vencimiento }}</div>
                    </button>
                </div>
            </div>

            <!-- Detalle Factura y Pasarelas -->
            <div v-if="selectedFactura">
                <button @click="selectedFactura = null" class="text-[#00c6ff] text-sm mb-6 hover:underline flex items-center gap-1.5 font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    Volver a las facturas
                </button>

                <!-- Detalle -->
                <div class="bg-white/[0.03] border border-white/[0.08] rounded-2xl overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-[#193388]/20 to-transparent px-6 py-4 border-b border-white/[0.06]">
                        <h4 class="text-sm font-semibold text-[#00c6ff] space-grotesk uppercase tracking-widest">
                            Detalle de la Factura
                        </h4>
                    </div>
                    <div class="divide-y divide-white/[0.04]">
                        <div class="flex px-6 py-3.5">
                            <span class="w-1/3 text-white/40 text-sm font-medium">Cliente</span>
                            <span class="flex-1 text-white/90 text-sm">@{{ fullname }}</span>
                        </div>
                        <div class="flex px-6 py-3.5 bg-white/[0.015]">
                            <span class="w-1/3 text-white/40 text-sm font-medium">Identificación</span>
                            <span class="flex-1 text-white/90 text-sm">@{{ selectedFactura.nit }}</span>
                        </div>
                        <div class="flex px-6 py-3.5">
                            <span class="w-1/3 text-white/40 text-sm font-medium">N° Factura</span>
                            <span class="flex-1 text-white/90 text-sm">@{{ selectedFactura.factura }}</span>
                        </div>
                        <div class="flex px-6 py-3.5 bg-white/[0.015]">
                            <span class="w-1/3 text-white/40 text-sm font-medium">Emisión</span>
                            <span class="flex-1 text-white/90 text-sm">@{{ selectedFactura.emision }}</span>
                        </div>
                        <div class="flex px-6 py-3.5">
                            <span class="w-1/3 text-white/40 text-sm font-medium">Vencimiento</span>
                            <span class="flex-1 text-white/90 text-sm">@{{ selectedFactura.vencimiento }}</span>
                        </div>
                        <div class="flex px-6 py-4 bg-gradient-to-r from-[#00c6ff]/5 to-transparent">
                            <span class="w-1/3 text-white/40 text-sm font-medium">Total a Pagar</span>
                            <span class="flex-1 text-xl font-bold text-[#00c6ff]">@{{ formatCurrency(selectedFactura.price) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Pasarelas -->
                <div v-if="pasarelas.length > 0">
                    <h4 class="text-sm font-semibold text-white/50 mb-4 text-center space-grotesk uppercase tracking-widest">
                        Selecciona tu método de pago
                    </h4>
                    <div class="flex flex-wrap justify-center gap-3">
                        <template v-for="p in pasarelas">
                            <!-- Wompi -->
                            <div v-if="p.nombre === 'WOMPI'">
                                <form action="https://checkout.wompi.co/p/" method="GET" :id="'form-wompi'" class="hidden">
                                    <input type="hidden" name="public-key" :value="p.api_key" />
                                    <input type="hidden" name="currency" value="COP" />
                                    <input type="hidden" name="amount-in-cents" :value="Math.round(selectedFactura.price * 100)" />
                                    <input type="hidden" name="reference" :value="`${empresaPrefix}-${selectedFactura.factura}`" />
                                    <input type="hidden" name="redirect-url" :value="`${baseUrl}/portal-pagos`" />
                                    <input type="hidden" name="signature:integrity" :value="wompiSignature" />
                                </form>
                                <button @click="confirmGateway('form-wompi', 'Wompi')" class="bg-gradient-to-br from-[#00a84f] to-[#00d35e] text-white px-7 py-3.5 rounded-xl font-bold text-sm hover:-translate-y-0.5 hover:shadow-[0_6px_20px_rgba(0,211,94,0.25)] transition-all flex items-center gap-2.5 min-w-[200px] justify-center">
                                    Pagar con Wompi
                                </button>
                            </div>

                            <!-- PayU -->
                            <div v-if="p.nombre === 'PayU'">
                                <form action="https://checkout.payulatam.com/ppp-web-gateway-payu/" method="POST" :id="'form-payu'" class="hidden">
                                    <input name="merchantId" type="hidden" :value="p.merchantId" />
                                    <input name="accountId" type="hidden" :value="p.accountId" />
                                    <input name="description" type="hidden" :value="`Factura ${selectedFactura.factura}`" />
                                    <input name="referenceCode" type="hidden" :value="`${empresaPrefix}-${selectedFactura.factura}`" />
                                    <input name="amount" type="hidden" :value="selectedFactura.price" />
                                    <input name="tax" type="hidden" value="0" />
                                    <input name="taxReturnBase" type="hidden" value="0" />
                                    <input name="currency" type="hidden" value="COP" />
                                    <input name="signature" type="hidden" :value="payuSignature" />
                                    <input name="test" type="hidden" value="0" />
                                    <input name="buyerFullName" type="hidden" :value="fullname" />
                                    <input name="telephone" type="hidden" :value="selectedFactura.celular" />
                                    <input name="buyerEmail" type="hidden" :value="selectedFactura.email || empresaEmail" />
                                    <input name="responseUrl" type="hidden" :value="`${baseUrl}/payu.php`" />
                                    <input name="confirmationUrl" type="hidden" :value="`${baseUrl}/pagos/payu`" />
                                </form>
                                <button @click="confirmGateway('form-payu', 'PayU')" class="bg-gradient-to-br from-[#a7c639] to-[#8ab419] text-white px-7 py-3.5 rounded-xl font-bold text-sm hover:-translate-y-0.5 hover:shadow-[0_6px_20px_rgba(167,198,57,0.25)] transition-all flex items-center gap-2.5 min-w-[200px] justify-center">
                                    Pagar con PayU
                                </button>
                            </div>

                            <!-- ePayco -->
                            <button v-if="p.nombre === 'ePayco'" @click="confirmEpayco(p)" class="bg-gradient-to-br from-[#0a3560] to-[#1565a0] text-white px-7 py-3.5 rounded-xl font-bold text-sm hover:-translate-y-0.5 hover:shadow-[0_6px_20px_rgba(21,101,160,0.25)] transition-all flex items-center gap-2.5 min-w-[200px] justify-center">
                                Pagar con ePayco
                            </button>

                            <!-- ComboPay -->
                            <button v-if="p.nombre === 'ComboPay'" @click="handleComboPay(p)" :disabled="comboPayLoading" class="bg-gradient-to-br from-[#5a20d0] to-[#8040f0] text-white px-7 py-3.5 rounded-xl font-bold text-sm hover:-translate-y-0.5 hover:shadow-[0_6px_20px_rgba(128,64,240,0.25)] transition-all flex items-center gap-2.5 min-w-[200px] justify-center disabled:opacity-60 disabled:cursor-wait">
                                <span v-if="comboPayLoading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                                <span v-else>Pagar con ComboPay</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

        </div>
        
        <!-- Overlay Loading Global -->
        <div v-if="loading" class="fixed inset-0 z-[9999] bg-[#05071a]/85 backdrop-blur-sm flex flex-col items-center justify-center gap-4">
            <div class="w-10 h-10 border-[3px] border-white/10 border-t-[#00c6ff] rounded-full animate-spin"></div>
            <span class="text-white/50 space-grotesk tracking-[0.2em] uppercase text-xs">Procesando...</span>
        </div>
    </main>

    <footer class="border-t border-white/[0.04] pt-14 pb-8 bg-gradient-to-t from-black/50 to-transparent relative z-10">
        <div class="max-w-6xl mx-auto px-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-10">
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-[#00c6ff]/[0.08] text-[#00c6ff] flex items-center justify-center text-lg shrink-0">📍</div>
                <div>
                    <h6 class="text-white/70 text-xs font-semibold tracking-wider mb-1">UBICACIÓN</h6>
                    <p class="text-white/40 text-sm leading-relaxed whitespace-pre-line">{{ $datosEmpresa['direccion'] }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-[#00c6ff]/[0.08] text-[#00c6ff] flex items-center justify-center text-lg shrink-0">📞</div>
                <div>
                    <h6 class="text-white/70 text-xs font-semibold tracking-wider mb-1">TELÉFONO</h6>
                    <p class="text-white/40 text-sm leading-relaxed whitespace-pre-line">{{ $datosEmpresa['telefono'] }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-[#00c6ff]/[0.08] text-[#00c6ff] flex items-center justify-center text-lg shrink-0">✉️</div>
                <div>
                    <h6 class="text-white/70 text-xs font-semibold tracking-wider mb-1">CORREO</h6>
                    <p class="text-white/40 text-sm leading-relaxed whitespace-pre-line">{{ $datosEmpresa['email'] }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-lg bg-[#00c6ff]/[0.08] text-[#00c6ff] flex items-center justify-center text-lg shrink-0">⏰</div>
                <div>
                    <h6 class="text-white/70 text-xs font-semibold tracking-wider mb-1">HORARIO</h6>
                    <p class="text-white/40 text-sm leading-relaxed whitespace-pre-line">Lunes a Viernes<br>8:00 AM - 6:00 PM</p>
                </div>
            </div>
        </div>
        <div class="text-center text-white/25 text-xs border-t border-white/[0.04] pt-6 mx-6">
            Copyright © {{ date('Y') }} Todos los derechos reservados
        </div>
    </footer>

    @if($datosEmpresa['whatsapp'])
    <a href="https://api.whatsapp.com/send?phone={{ $datosEmpresa['whatsapp'] }}&text=Hola,%20necesito%20ayuda%20con%20mi%20factura" target="_blank" rel="noreferrer" class="fixed bottom-6 right-6 w-14 h-14 bg-[#25D366] text-white rounded-full flex items-center justify-center shadow-[0_4px_16px_rgba(37,211,102,0.4)] hover:scale-110 hover:shadow-[0_6px_24px_rgba(37,211,102,0.5)] transition-all z-50">
        <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" /></svg>
    </a>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
    <script>
        const sweetAlert = async (opts) => {
            return Swal.fire({
                ...opts,
                confirmButtonColor: '#00c6ff',
                cancelButtonColor: '#6b7280',
                background: '#0c1029',
                color: '#f0f4ff',
            });
        };

        new Vue({
            el: '#app',
            data: {
                identificacion: '',
                loading: false,
                facturas: [],
                pasarelas: [],
                selectedFactura: null,
                wompiSignature: '',
                payuSignature: '',
                comboPayLoading: false,
                searched: false,
                moneda: '{{ $datosEmpresa["moneda"] }}',
                empresaPrefix: '{{ $datosEmpresa["prefix"] }}',
                empresaEmail: '{{ $datosEmpresa["email"] }}',
                baseUrl: window.location.origin
            },
            computed: {
                fullname() {
                    if (!this.selectedFactura) return '';
                    return `${this.selectedFactura.nombre} ${this.selectedFactura.apellido1} ${this.selectedFactura.apellido2}`.trim();
                }
            },
            methods: {
                formatCurrency(value) {
                    return `${this.moneda} ${new Intl.NumberFormat('es-CO').format(value)}`;
                },
                async consultar() {
                    if (!this.identificacion.trim()) {
                        sweetAlert({
                            title: 'Ingresa tu número de identificación',
                            text: 'Escribe tu cédula o NIT para consultar las facturas pendientes.',
                            icon: 'warning',
                            timer: 4000,
                        });
                        return;
                    }

                    this.loading = true;
                    this.selectedFactura = null;
                    this.facturas = [];
                    this.pasarelas = [];
                    this.searched = false;

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const res = await fetch('/portal-pagos/consultar', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({ identificacion: this.identificacion.trim() })
                        });
                        const data = await res.json();

                        if (!data.contrato || data.contrato.length === 0) {
                            this.searched = true;
                            sweetAlert({
                                title: 'Sin facturas pendientes',
                                text: 'No se encontraron facturas pendientes asociadas a esta identificación.',
                                icon: 'info',
                            });
                        } else {
                            this.facturas = data.contrato;
                            this.pasarelas = data.pasarelas || [];
                            this.searched = true;
                        }
                    } catch (e) {
                        sweetAlert({
                            title: 'Error de conexión',
                            text: 'Ocurrió un problema al consultar. Intenta nuevamente.',
                            icon: 'error',
                        });
                    } finally {
                        this.loading = false;
                    }
                },
                async seleccionarFactura(factura) {
                    this.selectedFactura = factura;
                    this.loading = true;
                    this.wompiSignature = '';
                    this.payuSignature = '';

                    for (const p of this.pasarelas) {
                        try {
                            if (p.nombre === 'WOMPI' && p.integrity) {
                                const reference = `${this.empresaPrefix}-${factura.factura}`;
                                const amountInCents = Math.round(factura.price * 100);
                                const integrityString = `${reference}${amountInCents}COP${p.integrity}`;
                                const encoded = new TextEncoder().encode(integrityString);
                                const hashBuffer = await crypto.subtle.digest('SHA-256', encoded);
                                const hashHex = Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
                                this.wompiSignature = hashHex;
                            }

                            if (p.nombre === 'PayU' && p.api_key && p.merchantId) {
                                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                                const res = await fetch('/portal-pagos/hash-payu', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                                    body: JSON.stringify({
                                        api_key: p.api_key, merchantId: p.merchantId,
                                        referenceCode: `${this.empresaPrefix}-${factura.factura}`,
                                        amount: factura.price, currency: 'COP'
                                    })
                                });
                                const data = await res.json();
                                if (data.hash) this.payuSignature = data.hash;
                            }
                        } catch (e) { }
                    }
                    this.loading = false;
                },
                async confirmGateway(formId, gatewayName) {
                    const result = await sweetAlert({
                        title: `Serás redireccionado a ${gatewayName}`,
                        text: '¿Deseas continuar con el pago?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, continuar',
                        cancelButtonText: 'Cancelar',
                    });

                    if (result.isConfirmed) {
                        document.getElementById(formId).submit();
                    }
                },
                async confirmEpayco(p) {
                    const result = await sweetAlert({
                        title: `Serás redireccionado a ePayco`,
                        text: '¿Deseas continuar con el pago?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, continuar',
                        cancelButtonText: 'Cancelar',
                    });

                    if (result.isConfirmed) {
                        if (window.ePayco) {
                            const handler = window.ePayco.checkout.configure({
                                key: p.api_key,
                                test: false,
                            });
                            handler.open({
                                external: 'true',
                                amount: this.selectedFactura.price,
                                name: `${this.empresaPrefix}-${this.selectedFactura.factura}`,
                                description: `${this.empresaPrefix}-${this.selectedFactura.factura}`,
                                currency: 'cop',
                                country: 'co',
                                email_billing: this.selectedFactura.email || this.empresaEmail,
                                name_billing: this.fullname,
                                address_billing: this.selectedFactura.direccion,
                                type_doc_billing: 'cc',
                                mobilephone_billing: this.selectedFactura.celular,
                                number_doc_billing: this.selectedFactura.nit,
                                response: `${this.baseUrl}/epayco.php`,
                                confirmation: `${this.baseUrl}/pagos/epayco`,
                                methodconfirmation: 'post',
                            });
                        }
                    }
                },
                async handleComboPay(p) {
                    if (!p.client_id || !p.client_secret) {
                        sweetAlert({ title: 'Credenciales incompletas', icon: 'error' });
                        return;
                    }
                    this.comboPayLoading = true;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        const tokenRes = await fetch(`/token-combopay?client_id=${p.client_id}&client_secret=${p.client_secret}&user=${p.user}&pass=${p.pass}`, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': token }
                        });
                        const dataToken = await tokenRes.json();

                        if (!dataToken.access_token) throw new Error();

                        const tipIden = (this.selectedFactura.tip_iden == 3 || this.selectedFactura.tip_iden == 4) ? 'CC' : 'NIT';
                        const linkRes = await fetch('/combopay/payment-link', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({
                                access_token: dataToken.access_token,
                                data: {
                                    value: this.selectedFactura.price,
                                    description: `Factura ${this.selectedFactura.factura} - ${this.fullname}`,
                                    invoice: this.selectedFactura.factura,
                                    url_data_return: `${this.baseUrl}/pagos/combopay`,
                                    url_client_redirect: `${this.baseUrl}/portal-pagos`,
                                    name: this.fullname,
                                    document_type: tipIden,
                                    customer_phone_number: this.selectedFactura.celular,
                                    document: this.selectedFactura.nit,
                                    customer_address: this.selectedFactura.direccion,
                                }
                            })
                        });
                        const dataLink = await linkRes.json();

                        if (!dataLink.payment_link) throw new Error();

                        const result = await sweetAlert({
                            title: 'Serás redireccionado a ComboPay',
                            text: '¿Deseas continuar con el pago?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, continuar',
                        });

                        if (result.isConfirmed) {
                            window.location.href = dataLink.payment_link;
                        }

                    } catch (e) {
                        sweetAlert({
                            title: 'Error en ComboPay',
                            text: 'No se pudo generar el enlace de pago.',
                            icon: 'error',
                        });
                    } finally {
                        this.comboPayLoading = false;
                    }
                }
            }
        });
    </script>
</body>
</html>
