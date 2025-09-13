<?php
require_once 'includes/init.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>bloodbrood</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        :root {
            --blood-primary: #8b0000;
            --blood-secondary: #dc143c;
            --blood-dark: #4b0000;
            --blood-light: #ff6b6b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            cursor: none;
            position: relative;
            background: radial-gradient(ellipse at center, #0a0a0a 0%, #000000 100%);
        }
        
        /* WebGL Canvas для продвинутых эффектов */
        #webgl-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -3;
            opacity: 0.5;
        }
        
        /* Кровавый фон с улучшенными эффектами */
        .blood-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -2;
            background: 
                radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%), 
                    rgba(139, 0, 0, 0.08) 0%, 
                    transparent 35%),
                radial-gradient(circle at 30% 60%, 
                    rgba(220, 20, 60, 0.02) 0%, 
                    transparent 40%),
                radial-gradient(circle at 70% 40%, 
                    rgba(139, 0, 0, 0.02) 0%, 
                    transparent 40%);
            transition: background 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        /* Продвинутый курсор с физикой */
        .custom-cursor {
            position: fixed;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: radial-gradient(circle, 
                rgba(255, 0, 0, 0.9) 0%, 
                rgba(139, 0, 0, 0.7) 50%, 
                rgba(75, 0, 0, 0.4) 80%,
                transparent 100%);
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 9999;
            transition: all 0.08s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            filter: drop-shadow(0 0 10px rgba(255, 0, 0, 0.5));
            border: 1px solid rgba(255, 0, 0, 0.3);
            mix-blend-mode: screen;
        }
        
        .custom-cursor::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, 
                rgba(255, 0, 0, 0.6) 0%, 
                transparent 70%);
            transform: translate(-50%, -50%);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            50% { transform: translate(-50%, -50%) scale(1.5); opacity: 0; }
        }
        
        /* Контейнер с логотипом */
        .logo-container {
            position: relative;
            z-index: 10;
        }
        
        .logo {
            font-size: 8rem;
            color: var(--text-muted);
            font-weight: bold;
            text-shadow: 
                0 0 20px rgba(0, 0, 0, 0.9),
                0 0 40px rgba(139, 0, 0, 0.5),
                0 0 60px rgba(139, 0, 0, 0.3);
            letter-spacing: 0.08em;
            cursor: none;
            transition: all 0.3s ease;
            user-select: none;
            position: relative;
            font-family: 'Courier New', monospace;
            filter: contrast(1.2) brightness(0.9);
            transform-style: preserve-3d;
            perspective: 1000px;
        }
        
        .logo:hover {
            text-shadow: 
                0 0 30px rgba(0, 0, 0, 0.9),
                0 0 60px rgba(139, 0, 0, 0.7),
                0 0 90px rgba(139, 0, 0, 0.5);
            transform: scale(1.02) rotateX(-2deg);
            filter: contrast(1.3) brightness(1);
        }
        
        /* Улучшенные буквы с кровавыми эффектами */
        .blood-letter {
            position: relative;
            display: inline-block;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            filter: blur(var(--blur-amount, 1.5px)) brightness(var(--brightness, 0.7));
            transform: translateZ(0) scale(var(--scale, 1));
            will-change: transform, filter;
        }
        
        .blood-letter::before {
            content: attr(data-letter);
            position: absolute;
            left: 0;
            top: 0;
            color: transparent;
            background: linear-gradient(180deg, 
                var(--blood-light) 0%, 
                var(--blood-secondary) 40%, 
                var(--blood-primary) 70%, 
                var(--blood-dark) 100%);
            background-clip: text;
            -webkit-background-clip: text;
            opacity: 0;
            transition: opacity 0.4s ease;
            filter: drop-shadow(0 2px 4px rgba(139, 0, 0, 0.8));
        }
        
        .blood-letter:hover::before {
            opacity: 1;
        }
        
        /* Мягкое круглое свечение вместо прямоугольника */
        .blood-glow {
            position: absolute;
            width: 200%;
            height: 200%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: radial-gradient(circle at center,
                rgba(139, 0, 0, 0.06) 0%,
                rgba(139, 0, 0, 0.03) 20%,
                rgba(139, 0, 0, 0.01) 40%,
                transparent 60%);
            opacity: 0;
            transition: opacity 0.6s ease;
            pointer-events: none;
            z-index: -1;
            border-radius: 50%;
            filter: blur(10px);
        }
        
        .blood-letter:hover .blood-glow {
            opacity: 1;
            animation: softPulse 3s ease-in-out infinite;
        }
        
        @keyframes softPulse {
            0%, 100% { 
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
            50% { 
                transform: translate(-50%, -50%) scale(1.1);
                opacity: 0.8;
            }
        }
        
        /* Продвинутая система капель крови */
        .blood-drop {
            position: absolute;
            width: 6px;
            height: 8px;
            background: linear-gradient(180deg, 
                var(--blood-secondary) 0%, 
                var(--blood-primary) 60%, 
                var(--blood-dark) 100%);
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            box-shadow: 
                inset -1px -1px 2px rgba(0, 0, 0, 0.5),
                0 2px 4px rgba(139, 0, 0, 0.6);
            z-index: 100;
            pointer-events: none;
            transform-origin: top center;
        }
        
        @keyframes bloodDrip {
            0% { 
                transform: translateY(0) scale(1) rotateZ(0deg); 
                opacity: 0.9;
            }
            10% {
                transform: translateY(2px) scale(1.1, 0.9) rotateZ(-2deg);
            }
            30% {
                transform: translateY(10px) scale(0.9, 1.2) rotateZ(1deg);
            }
            60% {
                transform: translateY(25px) scale(0.8, 1.3) rotateZ(-1deg);
                opacity: 0.8;
            }
            100% { 
                transform: translateY(40px) scale(0.6, 1.5) rotateZ(0deg); 
                opacity: 0;
            }
        }
        
        /* Брызги крови при падении */
        .blood-splash {
            position: absolute;
            width: 30px;
            height: 30px;
            pointer-events: none;
            z-index: 99;
        }
        
        .splash-particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: var(--blood-primary);
            border-radius: 50%;
            box-shadow: 0 0 2px rgba(139, 0, 0, 0.8);
        }
        
        @keyframes splashOut {
            0% {
                transform: translate(0, 0) scale(1);
                opacity: 1;
            }
            100% {
                transform: translate(var(--dx), var(--dy)) scale(0.3);
                opacity: 0;
            }
        }
        
        /* Угловые тексты с эффектами */
        .corner-text {
            position: absolute;
            color: var(--text-muted);
            font-size: 1.2rem;
            font-weight: 500;
            opacity: 0.7;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            cursor: none;
            font-family: 'Courier New', monospace;
            filter: blur(var(--text-blur, 0.8px)) brightness(var(--text-brightness, 0.8));
            text-shadow: 0 0 8px rgba(0, 0, 0, 0.8);
        }
        
        .corner-text:hover {
            opacity: 1;
            transform: scale(1.05) translateZ(20px);
            filter: blur(0px) brightness(1.1);
            text-shadow: 
                0 0 10px rgba(0, 0, 0, 0.9),
                0 0 20px rgba(139, 0, 0, 0.5);
        }
        
        .top-left { top: 30px; left: 30px; }
        .top-right { top: 30px; right: 30px; }
        .bottom-left { bottom: 30px; left: 30px; }
        .bottom-right { bottom: 30px; right: 30px; }
        
        /* Секретные области */
        .secret-area {
            position: fixed;
            bottom: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
            z-index: 1001;
            opacity: 0;
        }
        
        .secret-corner {
            position: fixed;
            top: 0;
            left: 0;
            width: 20px;
            height: 20px;
            z-index: 1001;
            opacity: 0;
        }
        
        /* Продвинутые частицы крови */
        .blood-particle {
            position: absolute;
            background: radial-gradient(circle, 
                var(--blood-secondary) 0%, 
                var(--blood-primary) 50%, 
                transparent 100%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 50;
            filter: blur(0.5px);
            mix-blend-mode: multiply;
        }
        
        @keyframes floatBlood {
            0% { 
                transform: translateY(0px) rotateZ(0deg) scale(1); 
                opacity: 0.3; 
            }
            33% { 
                transform: translateY(-10px) rotateZ(120deg) scale(1.1); 
                opacity: 0.5; 
            }
            66% { 
                transform: translateY(-5px) rotateZ(240deg) scale(0.9); 
                opacity: 0.4; 
            }
            100% { 
                transform: translateY(0px) rotateZ(360deg) scale(1); 
                opacity: 0.3; 
            }
        }
        
        /* Кровавый след курсора */
        .cursor-trail {
            position: absolute;
            width: 8px;
            height: 8px;
            background: radial-gradient(circle, 
                rgba(255, 0, 0, 0.6) 0%, 
                rgba(139, 0, 0, 0.3) 50%, 
                transparent 100%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9998;
            animation: trailFade 1.5s ease-out forwards;
        }
        
        @keyframes trailFade {
            0% { 
                opacity: 0.6; 
                transform: scale(1) rotate(0deg); 
            }
            100% { 
                opacity: 0; 
                transform: scale(0.1) rotate(180deg); 
            }
        }
        
        /* Глитч эффект для атмосферы */
        @keyframes glitch {
            0%, 100% { 
                transform: translate(0);
                filter: hue-rotate(0deg);
            }
            20% { 
                transform: translate(-1px, 1px);
                filter: hue-rotate(90deg);
            }
            40% { 
                transform: translate(-1px, -1px);
                filter: hue-rotate(180deg);
            }
            60% { 
                transform: translate(1px, 1px);
                filter: hue-rotate(270deg);
            }
            80% { 
                transform: translate(1px, -1px);
                filter: hue-rotate(360deg);
            }
        }
        
        .glitch {
            animation: glitch 0.2s ease-in-out;
        }
        
        /* Анимация появления */
        @keyframes fadeIn {
            from { 
                opacity: 0; 
                filter: blur(10px); 
                transform: scale(0.9);
            }
            to { 
                opacity: 1; 
                filter: blur(0px); 
                transform: scale(1);
            }
        }
        
        .logo, .corner-text {
            animation: fadeIn 2.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        /* Шум и зерно для атмосферы */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-image: 
                radial-gradient(circle at 25% 25%, transparent 0%, rgba(255, 0, 0, 0.01) 50%, transparent 100%),
                radial-gradient(circle at 75% 75%, transparent 0%, rgba(139, 0, 0, 0.01) 50%, transparent 100%);
            opacity: 0.3;
            z-index: -1;
            pointer-events: none;
            animation: noise 0.2s infinite;
        }
        
        @keyframes noise {
            0%, 100% { transform: translate(0, 0); }
            10% { transform: translate(-1%, -1%); }
            20% { transform: translate(1%, 1%); }
            30% { transform: translate(-1%, 1%); }
            40% { transform: translate(1%, -1%); }
            50% { transform: translate(-0.5%, 0.5%); }
            60% { transform: translate(0.5%, -0.5%); }
            70% { transform: translate(-0.5%, -0.5%); }
            80% { transform: translate(0.5%, 0.5%); }
            90% { transform: translate(-1%, 0); }
        }
        
        @media (max-width: 768px) {
            .logo {
                font-size: 4rem;
            }
            .corner-text {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- SVG фильтры для текстур -->
    <svg style="position: absolute; width: 0; height: 0;">
        <defs>
            <filter id="liquid">
                <feTurbulence baseFrequency="0.01" numOctaves="2" result="noise"/>
                <feDisplacementMap in="SourceGraphic" in2="noise" scale="8"/>
            </filter>
        </defs>
    </svg>
    
    <canvas id="webgl-canvas"></canvas>
    <div class="blood-bg"></div>
    <div class="custom-cursor"></div>
    <div class="secret-area" id="secretArea"></div>
    <div class="secret-corner" id="secretCorner"></div>
    
    <div class="corner-text top-left">
        <span class="blood-letter" data-letter="G">G<div class="blood-glow"></div></span><span class="blood-letter" data-letter="e">e<div class="blood-glow"></div></span><span class="blood-letter" data-letter="t">t<div class="blood-glow"></div></span>
    </div>
    <div class="corner-text top-right">
        <span class="blood-letter" data-letter="k">k<div class="blood-glow"></div></span><span class="blood-letter" data-letter="y">y<div class="blood-glow"></div></span><span class="blood-letter" data-letter="s">s<div class="blood-glow"></div></span>
    </div>
    
    <div class="logo-container">
        <div class="logo">
            <span class="blood-letter" data-letter="b">b<div class="blood-glow"></div></span><span class="blood-letter" data-letter="l">l<div class="blood-glow"></div></span><span class="blood-letter" data-letter="o">o<div class="blood-glow"></div></span><span class="blood-letter" data-letter="o">o<div class="blood-glow"></div></span><span class="blood-letter" data-letter="d">d<div class="blood-glow"></div></span><span class="blood-letter" data-letter="b">b<div class="blood-glow"></div></span><span class="blood-letter" data-letter="r">r<div class="blood-glow"></div></span><span class="blood-letter" data-letter="o">o<div class="blood-glow"></div></span><span class="blood-letter" data-letter="o">o<div class="blood-glow"></div></span><span class="blood-letter" data-letter="d">d<div class="blood-glow"></div></span>
        </div>
    </div>
    
    <div class="corner-text bottom-left">
        <span class="blood-letter" data-letter="T">T<div class="blood-glow"></div></span><span class="blood-letter" data-letter="h">h<div class="blood-glow"></div></span><span class="blood-letter" data-letter="e">e<div class="blood-glow"></div></span><br>
        <span class="blood-letter" data-letter="F">F<div class="blood-glow"></div></span><span class="blood-letter" data-letter="u">u<div class="blood-glow"></div></span><span class="blood-letter" data-letter="c">c<div class="blood-glow"></div></span><span class="blood-letter" data-letter="k">k<div class="blood-glow"></div></span><br>
        <span class="blood-letter" data-letter="O">O<div class="blood-glow"></div></span><span class="blood-letter" data-letter="u">u<div class="blood-glow"></div></span><span class="blood-letter" data-letter="t">t<div class="blood-glow"></div></span><br>
        <span class="blood-letter" data-letter="O">O<div class="blood-glow"></div></span><span class="blood-letter" data-letter="f">f<div class="blood-glow"></div></span><br>
        <span class="blood-letter" data-letter="H">H<div class="blood-glow"></div></span><span class="blood-letter" data-letter="e">e<div class="blood-glow"></div></span><span class="blood-letter" data-letter="r">r<div class="blood-glow"></div></span><span class="blood-letter" data-letter="e">e<div class="blood-glow"></div></span>
    </div>
    
    <div class="corner-text bottom-right">
        <span class="blood-letter" data-letter="G">G<div class="blood-glow"></div></span><span class="blood-letter" data-letter="e">e<div class="blood-glow"></div></span><span class="blood-letter" data-letter="t">t<div class="blood-glow"></div></span><br>
        <span class="blood-letter" data-letter="T">T<div class="blood-glow"></div></span><span class="blood-letter" data-letter="h">h<div class="blood-glow"></div></span><span class="blood-letter" data-letter="e">e<div class="blood-glow"></div></span><br>
        <span class="blood-letter" data-letter="F">F<div class="blood-glow"></div></span><span class="blood-letter" data-letter="u">u<div class="blood-glow"></div></span><span class="blood-letter" data-letter="c">c<div class="blood-glow"></div></span><span class="blood-letter" data-letter="k">k<div class="blood-glow"></div></span><br>
        <span class="blood-letter" data-letter="O">O<div class="blood-glow"></div></span><span class="blood-letter" data-letter="u">u<div class="blood-glow"></div></span><span class="blood-letter" data-letter="t">t<div class="blood-glow"></div></span><br>
        <span class="blood-letter" data-letter="O">O<div class="blood-glow"></div></span><span class="blood-letter" data-letter="f">f<div class="blood-glow"></div></span><br>
        <span class="blood-letter" data-letter="H">H<div class="blood-glow"></div></span><span class="blood-letter" data-letter="e">e<div class="blood-glow"></div></span><span class="blood-letter" data-letter="r">r<div class="blood-glow"></div></span><span class="blood-letter" data-letter="e">e<div class="blood-glow"></div></span>
    </div>
    
    <div class="corner-text" style="position: absolute; top: 50%; left: 15%; transform: translateY(-50%);">
        <span class="blood-letter" data-letter="O">O<div class="blood-glow"></div></span><span class="blood-letter" data-letter="n">n<div class="blood-glow"></div></span><span class="blood-letter" data-letter="l">l<div class="blood-glow"></div></span><span class="blood-letter" data-letter="y">y<div class="blood-glow"></div></span>
    </div>
    
    <div class="corner-text" style="position: absolute; top: 50%; right: 15%; transform: translateY(-50%);">
        <span class="blood-letter" data-letter="F">F<div class="blood-glow"></div></span><span class="blood-letter" data-letter="o">o<div class="blood-glow"></div></span><span class="blood-letter" data-letter="r">r<div class="blood-glow"></div></span><br>
        <span class="blood-letter" data-letter="E">E<div class="blood-glow"></div></span><span class="blood-letter" data-letter="l">l<div class="blood-glow"></div></span><span class="blood-letter" data-letter="i">i<div class="blood-glow"></div></span><span class="blood-letter" data-letter="t">t<div class="blood-glow"></div></span><span class="blood-letter" data-letter="e">e<div class="blood-glow"></div></span>
    </div>

    <script>
        // переменные для курсора
        const cursor = document.querySelector('.custom-cursor');
        const bloodBg = document.querySelector('.blood-bg');
        const bloodLetters = document.querySelectorAll('.blood-letter');
        const bloodTrails = [];
        const maxTrails = 25;
        const activeDrops = new Map();
        
        let mouseX = 0, mouseY = 0;
        let cursorX = 0, cursorY = 0;
        let velocityX = 0, velocityY = 0;
        
        // физика курсора
        function updateCursor() {
            const targetX = mouseX;
            const targetY = mouseY;
            
            const dx = targetX - cursorX;
            const dy = targetY - cursorY;
            
            velocityX += dx * 0.15;
            velocityY += dy * 0.15;
            
            velocityX *= 0.85; // замедление
            velocityY *= 0.85;
            
            cursorX += velocityX;
            cursorY += velocityY;
            
            cursor.style.left = cursorX + 'px';
            cursor.style.top = cursorY + 'px';
            
            // обновляем фон
            const x = (cursorX / window.innerWidth) * 100;
            const y = (cursorY / window.innerHeight) * 100;
            bloodBg.style.setProperty('--mouse-x', x + '%');
            bloodBg.style.setProperty('--mouse-y', y + '%');
            
            updateLetterSharpness();
            
            requestAnimationFrame(updateCursor);
        }
        updateCursor();
        
        // четкость букв по расстоянию
        function updateLetterSharpness() {
            bloodLetters.forEach(letter => {
                const rect = letter.getBoundingClientRect();
                const letterCenterX = rect.left + rect.width / 2;
                const letterCenterY = rect.top + rect.height / 2;
                
                const distance = Math.sqrt(
                    Math.pow(cursorX - letterCenterX, 2) + 
                    Math.pow(cursorY - letterCenterY, 2)
                );
                
                const maxDistance = 200;
                const normalizedDistance = Math.min(distance / maxDistance, 1);
                
                const blurAmount = normalizedDistance * 3;
                const brightness = 0.6 + (1 - normalizedDistance) * 0.6;
                
                letter.style.setProperty('--blur-amount', blurAmount + 'px');
                letter.style.setProperty('--brightness', brightness);
                
                // дрожание при близости
                if (distance < 50) {
                    const intensity = (50 - distance) / 50;
                    const shakeX = (Math.random() - 0.5) * intensity * 1.5;
                    const shakeY = (Math.random() - 0.5) * intensity * 1.5;
                    const scale = 1 + intensity * 0.03;
                    letter.style.transform = `translate(${shakeX}px, ${shakeY}px)`;
                    letter.style.setProperty('--scale', scale);
                } else {
                    letter.style.transform = 'translate(0, 0)';
                    letter.style.setProperty('--scale', 1);
                }
            });
        }
        
        // создание капли крови
        function createBloodDrop(letter) {
            if (activeDrops.has(letter)) return;
            
            const rect = letter.getBoundingClientRect();
            const drop = document.createElement('div');
            drop.className = 'blood-drop';
            
            const randomX = rect.left + Math.random() * rect.width;
            drop.style.left = randomX + 'px';
            drop.style.top = (rect.bottom - 5) + 'px';
            
            const size = 4 + Math.random() * 4;
            drop.style.width = size + 'px';
            drop.style.height = size * 1.3 + 'px';
            
            const duration = 2 + Math.random() * 2;
            drop.style.animation = `bloodDrip ${duration}s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards`;
            
            document.body.appendChild(drop);
            activeDrops.set(letter, drop);
            
            setTimeout(() => {
                createBloodSplash(randomX, rect.bottom + 35);
                drop.remove();
                activeDrops.delete(letter);
            }, duration * 1000);
        }
        
        // создание брызг крови
        function createBloodSplash(x, y) {
            const splash = document.createElement('div');
            splash.className = 'blood-splash';
            splash.style.left = x + 'px';
            splash.style.top = y + 'px';
            
            for (let i = 0; i < 8; i++) {
                const particle = document.createElement('div');
                particle.className = 'splash-particle';
                
                const angle = (Math.PI * 2 * i) / 8 + (Math.random() - 0.5) * 0.5;
                const velocity = 10 + Math.random() * 20;
                const dx = Math.cos(angle) * velocity;
                const dy = Math.sin(angle) * velocity - 10;
                
                particle.style.setProperty('--dx', dx + 'px');
                particle.style.setProperty('--dy', dy + 'px');
                particle.style.animation = `splashOut 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards`;
                
                splash.appendChild(particle);
            }
            
            document.body.appendChild(splash);
            
            setTimeout(() => {
                splash.remove();
            }, 600);
        }
        
        // движение мыши
        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
            
            if (Math.random() > 0.9) {
                createCursorTrail(e.clientX, e.clientY);
            }
        });
        
        // создание следа курсора
        function createCursorTrail(x, y) {
            const trail = document.createElement('div');
            trail.className = 'cursor-trail';
            trail.style.left = x + 'px';
            trail.style.top = y + 'px';
            
            const size = 4 + Math.random() * 4;
            trail.style.width = size + 'px';
            trail.style.height = size + 'px';
            
            document.body.appendChild(trail);
            bloodTrails.push(trail);
            
            if (bloodTrails.length > maxTrails) {
                const oldTrail = bloodTrails.shift();
                oldTrail.remove();
            }
            
            setTimeout(() => {
                trail.remove();
                const index = bloodTrails.indexOf(trail);
                if (index > -1) {
                    bloodTrails.splice(index, 1);
                }
            }, 1500);
        }
        
        // создание плавающих частиц
        function createBloodParticle() {
            const particle = document.createElement('div');
            particle.className = 'blood-particle';
            
            const size = 2 + Math.random() * 4;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.left = Math.random() * window.innerWidth + 'px';
            particle.style.top = Math.random() * window.innerHeight + 'px';
            
            const duration = 8 + Math.random() * 8;
            particle.style.animation = `floatBlood ${duration}s ease-in-out infinite`;
            particle.style.animationDelay = Math.random() * duration + 's';
            
            document.body.appendChild(particle);
            
            setTimeout(() => {
                particle.remove();
            }, duration * 1000);
        }
        
        setInterval(createBloodParticle, 3000);
        
        // наведение на буквы
        bloodLetters.forEach(letter => {
            let dropInterval = null;
            
            letter.addEventListener('mouseenter', function() {
                createBloodDrop(this);
                dropInterval = setInterval(() => {
                    if (Math.random() > 0.3) {
                        createBloodDrop(this);
                    }
                }, 800);
                
                this.style.filter = 'drop-shadow(0 0 10px rgba(255, 0, 0, 0.6))';
                
                cursor.style.width = '24px';
                cursor.style.height = '24px';
                cursor.style.filter = 'drop-shadow(0 0 15px rgba(255, 0, 0, 0.8))';
            });
            
            letter.addEventListener('mouseleave', function() {
                if (dropInterval) {
                    clearInterval(dropInterval);
                    dropInterval = null;
                }
                
                this.style.filter = '';
                
                cursor.style.width = '18px';
                cursor.style.height = '18px';
                cursor.style.filter = 'drop-shadow(0 0 10px rgba(255, 0, 0, 0.5))';
            });
            
            letter.addEventListener('click', function(e) {
                const rect = this.getBoundingClientRect();
                for (let i = 0; i < 3; i++) {
                    setTimeout(() => {
                        createBloodSplash(
                            rect.left + Math.random() * rect.width,
                            rect.top + Math.random() * rect.height
                        );
                    }, i * 100);
                }
                
                this.classList.add('glitch');
                setTimeout(() => {
                    this.classList.remove('glitch');
                }, 200);
            });
        });
        
        // секретные входы
        let secretHoverCount = 0;
        let secretTimer = null;
        let cornerTimer = null;
        
        const secretArea = document.getElementById('secretArea');
        const secretCorner = document.getElementById('secretCorner');
        
        secretArea.addEventListener('mouseover', () => {
            secretHoverCount++;
            
            if (secretHoverCount >= 3) {
                clearTimeout(secretTimer);
                window.location.href = 'auth.php?access=secret';
            }
            
            secretTimer = setTimeout(() => {
                secretHoverCount = 0;
            }, 2000);
        });
        
        secretCorner.addEventListener('mouseenter', () => {
            cornerTimer = setTimeout(() => {
                window.location.href = 'auth.php?access=corner';
            }, 5000);
        });
        
        secretCorner.addEventListener('mouseleave', () => {
            clearTimeout(cornerTimer);
        });
        
        // паттерн кликов
        let clickPattern = [];
        const correctPattern = [1, 3, 2, 4];
        
        document.querySelectorAll('.corner-text').forEach((corner, index) => {
            corner.addEventListener('click', () => {
                let cornerPosition;
                
                if (corner.classList.contains('top-left')) cornerPosition = 1;
                else if (corner.classList.contains('top-right')) cornerPosition = 2;
                else if (corner.classList.contains('bottom-left')) cornerPosition = 3;
                else if (corner.classList.contains('bottom-right')) cornerPosition = 4;
                else return;
                
                clickPattern.push(cornerPosition);
                
                if (clickPattern.length > 4) {
                    clickPattern = clickPattern.slice(-4);
                }
                
                if (clickPattern.length === 4 && 
                    clickPattern.every((val, idx) => val === correctPattern[idx])) {
                    
                    document.body.style.background = 'radial-gradient(ellipse at center, #1a0000 0%, #000000 100%)';
                    
                    setTimeout(() => {
                        window.location.href = 'auth.php?access=pattern';
                    }, 500);
                }
            });
        });
        
        // konami код
        const konamiCode = ["ArrowUp", "ArrowUp", "ArrowDown", "ArrowDown", "ArrowLeft", "ArrowRight", "ArrowLeft", "ArrowRight", "KeyB", "KeyA"];
        let konamiIndex = 0;
        
        document.addEventListener('keydown', function(e) {
            if (e.code === konamiCode[konamiIndex]) {
                konamiIndex++;
                
                if (konamiIndex === konamiCode.length) {
                    for (let i = 0; i < 50; i++) {
                        setTimeout(() => {
                            const letter = bloodLetters[Math.floor(Math.random() * bloodLetters.length)];
                            createBloodDrop(letter);
                        }, i * 50);
                    }
                    
                    setTimeout(() => {
                        window.location.href = 'auth.php?access=konami';
                    }, 2000);
                }
            } else {
                konamiIndex = 0;
            }
        });
        
        // защита от инспектора
        document.addEventListener('contextmenu', e => e.preventDefault());
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                (e.ctrlKey && e.shiftKey && e.key === 'J') ||
                (e.ctrlKey && e.key === 'U')) {
                e.preventDefault();
                
                bloodLetters.forEach((letter, index) => {
                    setTimeout(() => {
                        createBloodDrop(letter);
                    }, index * 100);
                });
            }
        });
        
        // webgl для эффектов
        const canvas = document.getElementById('webgl-canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        
        if (gl) {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        
        // начальная анимация
        window.addEventListener('load', () => {
            setTimeout(() => {
                const randomLetters = Array.from(bloodLetters)
                    .sort(() => Math.random() - 0.5)
                    .slice(0, 5);
                
                randomLetters.forEach((letter, index) => {
                    setTimeout(() => {
                        createBloodDrop(letter);
                    }, index * 300);
                });
            }, 1500);
        });
        
        // адаптивность
        window.addEventListener('resize', () => {
            if (canvas && gl) {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
        });
    </script>
</body>
</html>