@extends('layouts.app')

@section('style')
<style>
    /* Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Rajdhani:wght@400;600;700&display=swap');

    .integra2-wrapper {
        background: radial-gradient(circle at center, #0a0b10 0%, #030407 100%);
        color: #e0e0e0;
        font-family: 'Rajdhani', sans-serif;
        padding: 40px 20px;
        min-height: 100vh;
        overflow: hidden;
        position: relative;
    }

    .integra2-wrapper::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><filter id="noiseFilter"><feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(%23noiseFilter)" opacity="0.05"/></svg>');
        z-index: 0;
        pointer-events: none;
    }

    .integra2-header {
        text-align: center;
        margin-bottom: 60px;
        position: relative;
        z-index: 1;
        animation: fadeInDown 1s ease-out;
    }

    .integra2-header h1 {
        font-family: 'Orbitron', sans-serif;
        font-size: 4rem;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 5px;
        text-shadow: 0 0 10px #00f0ff, 0 0 20px #00f0ff, 0 0 40px #00f0ff;
        margin-bottom: 15px;
    }

    .integra2-header p {
        font-size: 1.5rem;
        color: #a0aab2;
        letter-spacing: 2px;
        max-width: 800px;
        margin: 0 auto;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto 80px auto;
        position: relative;
        z-index: 1;
    }

    .feature-card {
        background: rgba(15, 20, 30, 0.6);
        border: 1px solid rgba(0, 240, 255, 0.2);
        border-radius: 15px;
        padding: 30px;
        text-align: center;
        backdrop-filter: blur(10px);
        transition: all 0.4s ease;
        animation: fadeInUp 1s ease-out;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
    }

    .feature-card:hover {
        transform: translateY(-10px);
        border-color: #00f0ff;
        box-shadow: 0 0 20px rgba(0, 240, 255, 0.4), inset 0 0 15px rgba(0, 240, 255, 0.1);
    }

    .feature-card h3 {
        font-family: 'Orbitron', sans-serif;
        color: #00f0ff;
        font-size: 1.8rem;
        margin-bottom: 15px;
    }

    .feature-card p {
        font-size: 1.1rem;
        color: #cbd5e1;
        line-height: 1.6;
    }

    .images-showcase {
        max-width: 1200px;
        margin: 0 auto 80px auto;
        position: relative;
        z-index: 1;
    }

    .showcase-title {
        text-align: center;
        font-family: 'Orbitron', sans-serif;
        color: #fff;
        font-size: 2.5rem;
        margin-bottom: 40px;
        text-shadow: 0 0 10px rgba(255, 0, 255, 0.8);
    }

    .image-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    @media(max-width: 768px) {
        .image-grid {
            grid-template-columns: 1fr;
        }
    }

    .image-container {
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: transform 0.5s;
    }

    .image-container:hover {
        transform: scale(1.02);
        border-color: #ff00ff;
        box-shadow: 0 0 25px rgba(255, 0, 255, 0.5);
        z-index: 2;
    }

    .image-container img {
        width: 100%;
        height: auto;
        display: block;
        transition: opacity 0.5s;
    }

    .image-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(to top, rgba(10, 11, 16, 0.9), transparent);
        opacity: 0;
        transition: opacity 0.5s;
        display: flex;
        align-items: flex-end;
        padding: 20px;
    }

    .image-container:hover .image-overlay {
        opacity: 1;
    }

    .cta-section {
        text-align: center;
        padding: 60px 20px;
        background: linear-gradient(135deg, rgba(0, 240, 255, 0.1) 0%, rgba(255, 0, 255, 0.1) 100%);
        border-radius: 20px;
        max-width: 900px;
        margin: 0 auto;
        border: 1px solid rgba(255, 255, 255, 0.2);
        position: relative;
        z-index: 1;
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.8);
        animation: pulseGlow 4s infinite alternate;
    }

    .cta-text {
        font-size: 1.6rem;
        color: #fff;
        margin-bottom: 30px;
        font-weight: 600;
        letter-spacing: 1px;
    }

    .cta-price {
        font-family: 'Orbitron', sans-serif;
        font-size: 2.8rem;
        color: #ff00ff;
        text-shadow: 0 0 15px rgba(255, 0, 255, 0.8);
        display: block;
        margin: 15px 0 30px 0;
    }

    .cyber-btn {
        display: inline-block;
        padding: 18px 40px;
        font-family: 'Orbitron', sans-serif;
        font-size: 1.4rem;
        font-weight: 700;
        color: #0a0b10;
        background: #00f0ff;
        text-decoration: none;
        text-transform: uppercase;
        border-radius: 5px;
        position: relative;
        overflow: hidden;
        transition: all 0.3s;
        box-shadow: 0 0 15px #00f0ff, inset 0 0 10px rgba(255,255,255,0.5);
    }

    .cyber-btn:hover {
        background: #fff;
        color: #0a0b10;
        box-shadow: 0 0 30px #00f0ff, 0 0 60px #00f0ff;
        transform: scale(1.05);
    }

    .cyber-btn i {
        margin-right: 10px;
    }

    /* Animations */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulseGlow {
        0% { box-shadow: 0 0 30px rgba(0, 240, 255, 0.2); }
        100% { box-shadow: 0 0 50px rgba(255, 0, 255, 0.3); }
    }
</style>
@endsection

@section('content')
<div class="integra2-wrapper">
    <div class="integra2-header">
        <h1>Integra 2.0</h1>
        <p>El futuro de la gestión ISP ya está aquí. Más rápido, más inteligente, más poderoso.</p>
    </div>

    <div class="features-grid">
        <div class="feature-card" style="animation-delay: 0.2s;">
            <h3>Velocidad Extrema</h3>
            <p>Arquitectura optimizada para tiempos de respuesta instantáneos. Carga de datos un 300% más rápida para que tu operación nunca se detenga.</p>
        </div>
        <div class="feature-card" style="animation-delay: 0.4s;">
            <h3>Interfaz Unificada</h3>
            <p>Diseño Dark Mode inmersivo con dashboards personalizables. Toda la información crítica de tu ISP en una sola pantalla con visualización de datos en tiempo real.</p>
        </div>
        <div class="feature-card" style="animation-delay: 0.6s;">
            <h3>Nuevas Funcionalidades</h3>
            <p>Herramientas avanzadas de automatización, inteligencia artificial integrada para soporte al cliente y analíticas predictivas para el crecimiento de tu red.</p>
        </div>
    </div>

    <div class="images-showcase">
        <h2 class="showcase-title">Visualiza el Siguiente Nivel</h2>
        <div class="image-grid">
            <div class="image-container">
                <img src="https://redestvsat.net/images/I-1.png" alt="Dashboard Integra 2.0 - Vista 1" onerror="this.src='https://via.placeholder.com/800x450/0b0c10/00f0ff?text=INTEGRA+2.0+PREVIEW'">
                <div class="image-overlay">
                    <span style="color: #00f0ff; font-weight: bold; font-size: 1.2rem;">Panel de Control Unificado</span>
                </div>
            </div>
            <div class="image-container">
                <img src="https://redestvsat.net/images/I-2.png" alt="Dashboard Integra 2.0 - Vista 2" onerror="this.src='https://via.placeholder.com/800x450/0b0c10/ff00ff?text=NUEVA+INTERFAZ'">
                <div class="image-overlay">
                    <span style="color: #ff00ff; font-weight: bold; font-size: 1.2rem;">Analíticas Avanzadas</span>
                </div>
            </div>
            <div class="image-container">
                <img src="https://redestvsat.net/images/I-3.png" alt="Dashboard Integra 2.0 - Vista 3" onerror="this.src='https://via.placeholder.com/800x450/0b0c10/00f0ff?text=GESTIÓN+DE+RED'">
                <div class="image-overlay">
                    <span style="color: #00f0ff; font-weight: bold; font-size: 1.2rem;">Gestión de Nodos y Mapas</span>
                </div>
            </div>
            <div class="image-container">
                <img src="https://redestvsat.net/images/I-4.png" alt="Dashboard Integra 2.0 - Vista 4" onerror="this.src='https://via.placeholder.com/800x450/0b0c10/ff00ff?text=MODO+OSCURO+ATIVADO'">
                <div class="image-overlay">
                    <span style="color: #ff00ff; font-weight: bold; font-size: 1.2rem;">Experiencia Visual Inmersiva</span>
                </div>
            </div>
        </div>
    </div>

    <div class="cta-section">
        <div class="cta-text">Eleva tu ISP al siguiente nivel. Actualiza hoy y disfruta de la plataforma definitiva.</div>
        <div class="cta-price">OBTENER INTEGRA 2.0 POR 69.900 MÁS A TU FACTURA MENSUAL</div>
        <a href="https://wa.me/573054823310?text=Quiero%20obtener%20integra%202.0" target="_blank" class="cyber-btn">
            <i class="fab fa-whatsapp"></i> Lo Quiero Ahora
        </a>
    </div>
</div>
@endsection
