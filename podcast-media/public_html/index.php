<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');

function e(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Медиатека — Твоя музыка</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20">
    <link rel="icon" type="image/svg+xml" href="https://jsdelivr.net">

</head>
<body>

<div class="app-layout" id="appLayout">
    <!-- ЛЕВАЯ КОЛОНКА: МЕНЮ -->
    <aside class="sidebar">
        <div class="logo">
            <span class="logo-icon">⚡</span> Медиатека
        </div>
        
        <nav class="nav-menu">
            <button class="nav-item" id="navSearchBtn" data-view="search">
                <span class="icon">🔍</span> Поиск
            </button>
            <button class="nav-item active" id="navWaveBtn" data-view="wave">
                <span class="icon">🌊</span> Моя волна
            </button>
            <button class="nav-item" id="navForYouBtn" data-view="foryou">
                <span class="icon">🎵</span> Для вас и Тренды
            </button>
            <button class="nav-item">
                <span class="icon">🎤</span> Концерты
            </button>
            <button class="nav-item">
                <span class="icon">📚</span> Книги и подкасты
            </button>
            <button class="nav-item">
                <span class="icon">🧸</span> Детям
            </button>
            <button class="nav-item" id="navFavsBtn" data-view="favorites">
                <span class="icon">💚</span> Коллекция
            </button>
        </nav>

        <div class="sidebar-bottom">
            <div class="user-profile">
                <div class="avatar"></div>
                <span>Мой профиль</span>
            </div>
        </div>
    </aside>

    <!-- ПРАВАЯ ЧАСТЬ (МЕНЯЕТСЯ) -->
    <main class="main-content">
        
        <!-- ВКЛАДКА: ПОИСК -->
        <div id="view-search" class="view-container hidden">
            <div class="search-header">
                <div class="search-input-wrapper">
                    <span class="search-icon">🔍</span>
                    <form id="searchForm">
                        <input id="q" type="search" placeholder="Тот самый, будто бодрее, но не слишком..." autocomplete="off">
                    </form>
                </div>
            </div>
            
            <div class="quick-picks">
                <div class="quick-pick"><span class="qp-icon">🔥</span> Как отметить музыкой классный день</div>
                <div class="quick-pick"><span class="qp-icon">☀️</span> Просто включите что-то доброе и хорошее</div>
            </div>

            <div class="search-tabs">
                <button class="tab-btn active">Популярное</button>
                <button class="tab-btn">История</button>
            </div>

            <h2 class="section-title">Подборки музыки <span class="arrow">›</span></h2>
            <div class="cards-grid">
                <div class="genre-card" onclick="startCustomWave('Осенняя')">
                    <div class="genre-img" style="background: linear-gradient(135deg, #a86b39, #4b2c1a);"></div>
                    <span>Осенняя</span>
                </div>
                <div class="genre-card" onclick="startCustomWave('Настроения')">
                    <div class="genre-img" style="background: linear-gradient(135deg, #ffb347, #ffcc33);"></div>
                    <span>Настроения</span>
                </div>
                <div class="genre-card" onclick="startCustomWave('Занятия')">
                    <div class="genre-img" style="background: linear-gradient(135deg, #ff6b6b, #c0392b);"></div>
                    <span>Занятия</span>
                </div>
            </div>

            <div id="searchResultsWrap" class="hidden" style="margin-top: 30px;">
                <h2 class="section-title">Результаты поиска</h2>
                <div id="searchResultsList" class="track-list-view"></div>
            </div>
        </div>

        <!-- ВКЛАДКА: МОЯ ВОЛНА -->
        <div id="view-wave" class="view-container">
            <div class="wave-background"></div>
            
            <div class="wave-center-ui">
                <h1 class="huge-wave-title">Моя волна</h1>
                
                <!-- Обложка -->
                <img src="" alt="" class="wave-cover" id="waveCover">
                
                <!-- Строка с капсулой и кнопками -->
                <div class="wave-player-row">
                    
                    <!-- 1. БЛОК ГРОМКОСТИ -->
                    <div class="volume-control-wrapper">
                        <div class="volume-popup-container">
                            <div class="volume-track"></div>
                            <div class="volume-fill" id="waveVolFill"></div>
                            <input type="range" class="vertical-slider" id="waveVolSlider" min="0" max="100" value="100">
                        </div>
                        
                        <!-- Иконка динамика -->
                        <button class="circle-btn" id="waveVolIconBtn">
                            <svg id="icon-vol-on" viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                <path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                            </svg>
                            <svg id="icon-vol-off" viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                                <line x1="23" y1="9" x2="17" y2="15"></line>
                                <line x1="17" y1="9" x2="23" y2="15"></line>
                            </svg>
                        </button>
                    </div>

                    <!-- 2. КНОПКА ДИЗЛАЙКА -->
                    <button class="circle-btn" id="waveDislikeBtn" title="Не нравится">
                        <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                        </svg>
                    </button>

                    <!-- 3. ЦЕНТРАЛЬНАЯ КАПСУЛА (ПРОГРЕСС-БАР) -->
                    <div class="wave-progress-pill" id="waveProgressPill">
                        <div class="pill-fill" id="pillFill"></div>
                        <div class="pill-text">
                            <span id="waveArtistText">Исполнитель</span> — <span id="waveTitleText">Трек</span>
                        </div>
                        <input type="range" class="pill-seek-input" id="waveSeek" min="0" max="100" value="0">
                    </div>

                    <!-- 4. КНОПКА ИЗБРАННОГО -->
                    <button class="circle-btn wave-fav-btn" id="waveFavBtn" title="В избранное">
                        <svg class="heart-icon heart-empty" viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                        <svg class="heart-icon heart-filled" viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                    </button>

                    <!-- 5. МЕНЮ -->
                    <button class="circle-btn">•••</button>
                </div>

                <!-- КНОПКИ УПРАВЛЕНИЯ (НОВЫЕ SVG) -->
                <div class="wave-main-controls">
                    <!-- Назад -->
                    <button class="ctrl-icon" id="wavePrev" aria-label="Предыдущий">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
                            <path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/>
                        </svg>
                    </button>
                    
                    <!-- Плей / Пауза (внутри две иконки) -->
                    <button class="ctrl-play" id="wavePlay" aria-label="Воспроизведение">
                        <!-- Иконка Play (по умолчанию) -->
                        <svg id="icon-play" viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        <!-- Иконка Pause (скрыта) -->
                        <svg id="icon-pause" viewBox="0 0 24 24" width="32" height="32" fill="currentColor" style="display: none;">
                            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                        </svg>
                    </button>

                    <!-- Вперед -->
                    <button class="ctrl-icon" id="waveNext" aria-label="Следующий">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
                            <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                        </svg>
                    </button>
                </div>

                <!-- Подпись ИИ -->
                <div class="ai-hint">
                    <span class="ai-sparkle"></span> Трек «<span id="aiTrackName">...</span>» — это отличный выбор для вашей волны
                </div>
            </div>
        </div>

        <!-- ВКЛАДКА: КОЛЛЕКЦИЯ (ИЗБРАННОЕ) -->
        <div id="view-favorites" class="view-container hidden">
            <h1 class="page-title">Коллекция</h1>
            <p style="color: var(--muted); margin-bottom: 20px;">У вашей музыки есть цвет</p>
            <h2 class="section-title">Мне нравится</h2>
            <div id="favoritesList" class="track-list-view"></div>
        </div>

        <!-- ВКЛАДКА: ДЛЯ ВАС -->
        <div id="view-foryou" class="view-container hidden">
            <h1 class="page-title">Для вас и Тренды</h1>
            <div class="cards-grid" style="margin-top: 20px;">
                <div class="genre-card" style="height: 200px;">
                    <div class="genre-img" style="background: linear-gradient(135deg, #8E2DE2, #4A00E0);"></div>
                    <span>Ваши новинки</span>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- ГЛОБАЛЬНЫЙ НИЖНИЙ ПЛЕЕР -->
<div class="bottom-player hidden" id="bottomPlayer">
    <div class="bp-left">
        <img src="" alt="" class="bp-cover" id="bpCover">
        <div class="bp-info">
            <div class="bp-title" id="bpTitle">Трек</div>
            <div class="bp-artist" id="bpArtist">Исполнитель</div>
        </div>
        <button class="wave-fav-btn" id="bpFavBtn" title="В избранное">
            <svg class="heart-icon heart-empty" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            <svg class="heart-icon heart-filled" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="currentColor"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
        </button>
    </div>

    <div class="bp-center">
        <div class="bp-controls">
            <!-- Нижний плеер тоже переводим на SVG -->
            <button class="bp-btn" id="bpPrev">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
            </button>
            <button class="bp-play-btn" id="bpPlay">
                <svg id="bp-icon-play" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                <svg id="bp-icon-pause" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="display: none;"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            </button>
            <button class="bp-btn" id="bpNext">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
            </button>
        </div>
        <div class="bp-progress">
            <span id="bpTimeCur">0:00</span>
            <input type="range" id="bpSeek" min="0" max="100" value="0">
            <span id="bpTimeTot">0:00</span>
        </div>
    </div>

    <div class="bp-right">
        <div class="bp-volume">
            <span class="vol-icon" id="bpVolIcon">🔊</span>
            <input type="range" id="bpVolume" min="0" max="100" value="100">
        </div>
    </div>
</div>

<audio id="htmlAudioPlayer" preload="metadata"></audio>
<script src="assets/js/player.js?v=28"></script>
</body>
</html>