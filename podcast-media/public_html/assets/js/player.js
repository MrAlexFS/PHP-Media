let currentQueue = [];
let currentIndex = -1;
let currentTrack = null;
let favorites = JSON.parse(localStorage.getItem('mediateka_favs_v5') || '[]');
let dislikes = JSON.parse(localStorage.getItem('mediateka_dislikes') || '[]');

const svgEmpty = `<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>`;
const svgFilled = `<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="currentColor"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>`;

const audio = document.getElementById('htmlAudioPlayer');
const appLayout = document.getElementById('appLayout');
const views = {
    search: document.getElementById('view-search'),
    wave: document.getElementById('view-wave'),
    foryou: document.getElementById('view-foryou'),
    favorites: document.getElementById('view-favorites')
};
const navBtns = document.querySelectorAll('.nav-item[data-view]');

const bp = {
    player: document.getElementById('bottomPlayer'),
    cover: document.getElementById('bpCover'),
    title: document.getElementById('bpTitle'),
    artist: document.getElementById('bpArtist'),
    play: document.getElementById('bpPlay'),
    next: document.getElementById('bpNext'),
    prev: document.getElementById('bpPrev'),
    seek: document.getElementById('bpSeek'),
    volume: document.getElementById('bpVolume'),
    volIcon: document.getElementById('bpVolIcon'),
    favBtn: document.getElementById('bpFavBtn'),
    timeCur: document.getElementById('bpTimeCur'),
    timeTot: document.getElementById('bpTimeTot')
};

const pillFill = document.getElementById('pillFill');
const waveSeek = document.getElementById('waveSeek');

// ==================== НАВИГАЦИЯ ====================
function switchView(viewName) {
    Object.values(views).forEach(v => v.classList.add('hidden'));
    navBtns.forEach(btn => btn.classList.remove('active'));

    if (views[viewName]) views[viewName].classList.remove('hidden');
    const activeBtn = document.querySelector(`.nav-item[data-view="${viewName}"]`);
    if (activeBtn) activeBtn.classList.add('active');

    if (viewName === 'wave') {
        bp.player.classList.add('hidden');
        appLayout.classList.remove('has-bottom-player');
    } else {
        if (currentTrack) {
            bp.player.classList.remove('hidden');
            appLayout.classList.add('has-bottom-player');
        }
    }

    if (viewName === 'wave' && currentQueue.length === 0) loadWave();
    else if (viewName === 'favorites') renderFavorites();
}

navBtns.forEach(btn => btn.addEventListener('click', () => switchView(btn.dataset.view)));

// ==================== ПОИСК ====================
document.getElementById('searchForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const query = document.getElementById('q').value.trim();
    if (!query) return;

    document.getElementById('searchResultsWrap').classList.remove('hidden');
    const list = document.getElementById('searchResultsList');
    list.innerHTML = '<p style="color:var(--muted)">Ищем...</p>';

    try {
        const response = await fetch(`api.php?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        if (!data.tracks || data.tracks.length === 0) {
            list.innerHTML = '<p style="color:var(--muted)">Ничего не найдено</p>';
            return;
        }
        list.innerHTML = '';
        currentQueue = data.tracks; 
        data.tracks.forEach((track, index) => renderTrackRow(track, index, list, currentQueue));
    } catch (err) {
        list.innerHTML = '<p style="color:red">Ошибка поиска</p>';
    }
});

function renderTrackRow(track, index, container, queueArray) {
    const isFav = favorites.some(f => f.id === track.id);
    const row = document.createElement('div');
    row.className = 'track-row';
    const min = Math.floor((track.duration || 0) / 60);
    const sec = (track.duration || 0) % 60;
    const durStr = `${min}:${sec < 10 ? '0' : ''}${sec}`;

    row.innerHTML = `
        <img src="${track.cover || ''}" alt="">
        <div class="track-row-info">
            <div class="track-row-title">${track.title}</div>
            <div class="track-row-artist">${track.author}</div>
        </div>
        <div class="track-row-duration" style="color:var(--muted);font-size:13px;margin-right:20px;">${durStr}</div>
        <button class="list-fav-btn" style="background:transparent;border:none;color:${isFav?'var(--heart)':'#fff'};cursor:pointer;">
            ${isFav ? svgFilled : svgEmpty}
        </button>
    `;

    row.addEventListener('click', (e) => {
        if (e.target.closest('.list-fav-btn')) return;
        currentQueue = queueArray;
        playTrackAtIndex(index);
    });

    const favBtn = row.querySelector('.list-fav-btn');
    favBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleFavorite(track);
        if (container.id === 'favoritesList') {
            renderFavorites();
        } else {
            const nowFav = favorites.some(f => f.id === track.id);
            favBtn.style.color = nowFav ? 'var(--heart)' : '#fff';
            favBtn.innerHTML = nowFav ? svgFilled : svgEmpty;
        }
    });
    container.appendChild(row);
}

// ==================== КОЛЛЕКЦИЯ ====================
function renderFavorites() {
    const list = document.getElementById('favoritesList');
    list.innerHTML = '';
    if (favorites.length === 0) {
        list.innerHTML = '<p style="color:var(--muted)">В коллекции пока пусто.</p>';
        return;
    }
    favorites.forEach((track, index) => renderTrackRow(track, index, list, favorites));
}

// ==================== МОЯ ВОЛНА ====================
async function loadWave() {
    document.getElementById('waveArtistText').textContent = 'Подбираем...';
    document.getElementById('waveTitleText').textContent = 'треки';
    document.getElementById('waveCover').src = '';
    currentQueue = [];
    currentIndex = -1;

    try {
        const response = await fetch('api.php?wave=1');
        const data = await response.json();
        if (data.tracks && data.tracks.length > 0) {
            const filteredTracks = data.tracks.filter(t => !dislikes.includes(t.id));
            currentQueue = filteredTracks.length > 0 ? filteredTracks : data.tracks;
            playTrackAtIndex(0);
        }
    } catch (err) { console.error(err); }
}

async function startCustomWave(genre) {
    switchView('wave');
    document.getElementById('waveArtistText').textContent = 'Загрузка...';
    document.getElementById('waveTitleText').textContent = genre;
    currentQueue = [];
    
    try {
        const response = await fetch(`api.php?wave=1&q=${encodeURIComponent(genre)}`);
        const data = await response.json();
        if (data.tracks && data.tracks.length > 0) {
            const filteredTracks = data.tracks.filter(t => !dislikes.includes(t.id));
            currentQueue = filteredTracks.length > 0 ? filteredTracks : data.tracks;
            playTrackAtIndex(0);
        }
    } catch (err) {}
}

// ==================== ВОСПРОИЗВЕДЕНИЕ ====================
function playTrackAtIndex(index) {
    if (index < 0 || index >= currentQueue.length) return;
    currentIndex = index;
    currentTrack = currentQueue[index];

    document.getElementById('waveArtistText').textContent = currentTrack.author;
    document.getElementById('waveTitleText').textContent = currentTrack.title;
    document.getElementById('waveCover').src = currentTrack.cover || '';
    document.getElementById('aiTrackName').textContent = currentTrack.title;
    
    bp.cover.src = currentTrack.cover || '';
    bp.title.textContent = currentTrack.title;
    bp.artist.textContent = currentTrack.author;
    updateFavUI();
    updateDislikeUI();

    if (views.wave.classList.contains('hidden')) {
        bp.player.classList.remove('hidden');
        appLayout.classList.add('has-bottom-player');
    }

    audio.pause();
    if (currentTrack.isLocal) audio.src = currentTrack.audio;
    else audio.src = `stream_remote.php?id=${currentTrack.ya_id}`;
    
    audio.load();
    audio.play().catch(() => {
        // В случае ошибки возвращаем иконку Play
        setPlayState(false);
    });
}

// ==================== УПРАВЛЕНИЕ ====================
bp.play.addEventListener('click', () => { if (audio.src) audio.paused ? audio.play() : audio.pause(); });
document.getElementById('wavePlay').addEventListener('click', () => { if (audio.src) audio.paused ? audio.play() : audio.pause(); });

function playNext() {
    if (currentQueue.length > 0) {
        let nextIdx = (currentIndex + 1) % currentQueue.length;
        if (nextIdx === 0 && !views.wave.classList.contains('hidden')) loadWave();
        else playTrackAtIndex(nextIdx);
    }
}
function playPrev() {
    if (currentQueue.length > 0) playTrackAtIndex((currentIndex - 1 + currentQueue.length) % currentQueue.length);
}

bp.next.addEventListener('click', playNext);
bp.prev.addEventListener('click', playPrev);
document.getElementById('waveNext').addEventListener('click', playNext);
document.getElementById('wavePrev').addEventListener('click', playPrev);

// ==================== ЛОГИКА ГРОМКОСТИ ====================
function setVolume(val) {
    audio.volume = val;
    bp.volume.value = val * 100;
    document.getElementById('waveVolSlider').value = val * 100;
    
    const fill = document.getElementById('waveVolFill');
    if (fill) {
        fill.style.height = (val * 150) + 'px';
    }
    
    updateVolumeIcons(val);
}

bp.volume.addEventListener('input', (e) => setVolume(e.target.value / 100));
document.getElementById('waveVolSlider').addEventListener('input', (e) => setVolume(e.target.value / 100));

const toggleMute = () => {
    if (audio.volume > 0) {
        audio.dataset.prevVolume = audio.volume;
        setVolume(0);
    } else {
        setVolume(audio.dataset.prevVolume || 1);
    }
};

document.getElementById('bpVolIcon').addEventListener('click', toggleMute);
document.getElementById('waveVolIconBtn').addEventListener('click', toggleMute);

function updateVolumeIcons(vol) {
    const iconText = vol === 0 ? '🔇' : (vol < 0.5 ? '🔈' : '🔊');
    bp.volIcon.textContent = iconText;
    
    const iconOn = document.getElementById('icon-vol-on');
    const iconOff = document.getElementById('icon-vol-off');
    
    if (vol === 0) {
        iconOn.style.display = 'none';
        iconOff.style.display = 'block';
    } else {
        iconOn.style.display = 'block';
        iconOff.style.display = 'none';
    }
}

// ==================== ДИЗЛАЙК ====================
document.getElementById('waveDislikeBtn').addEventListener('click', () => {
    if (!currentTrack) return;
    
    const btn = document.getElementById('waveDislikeBtn');
    btn.classList.toggle('active');
    
    const isDisliked = btn.classList.contains('active');
    
    if (isDisliked) {
        if (!dislikes.includes(currentTrack.id)) dislikes.push(currentTrack.id);
        localStorage.setItem('mediateka_dislikes', JSON.stringify(dislikes));
        
        if (favorites.some(f => f.id === currentTrack.id)) {
            toggleFavorite(currentTrack);
        }

        setTimeout(() => playNext(), 300);
    } else {
        dislikes = dislikes.filter(id => id !== currentTrack.id);
        localStorage.setItem('mediateka_dislikes', JSON.stringify(dislikes));
    }
});

function updateDislikeUI() {
    const btn = document.getElementById('waveDislikeBtn');
    if (!currentTrack) {
        btn.classList.remove('active');
        return;
    }
    const isDisliked = dislikes.includes(currentTrack.id);
    btn.classList.toggle('active', isDisliked);
}

// ==================== СОСТОЯНИЕ ПЛЕЕРА (PLAY/PAUSE) ====================
function setPlayState(isPlaying) {
    const wavePlayBtn = document.getElementById('wavePlay');
    const waveIconPlay = document.getElementById('icon-play');
    const waveIconPause = document.getElementById('icon-pause');

    const bpPlayBtn = document.getElementById('bpPlay');
    const bpIconPlay = document.getElementById('bp-icon-play');
    const bpIconPause = document.getElementById('bp-icon-pause');

    if (isPlaying) {
        // Включаем паузу
        wavePlayBtn.classList.add('is-playing');
        waveIconPlay.style.display = 'none';
        waveIconPause.style.display = 'block';

        bpPlayBtn.classList.add('is-playing');
        bpIconPlay.style.display = 'none';
        bpIconPause.style.display = 'block';
    } else {
        // Включаем плей
        wavePlayBtn.classList.remove('is-playing');
        waveIconPlay.style.display = 'block';
        waveIconPause.style.display = 'none';

        bpPlayBtn.classList.remove('is-playing');
        bpIconPlay.style.display = 'block';
        bpIconPause.style.display = 'none';
    }
}

audio.addEventListener('play', () => setPlayState(true));
audio.addEventListener('pause', () => setPlayState(false));

audio.addEventListener('ended', playNext);

audio.addEventListener('timeupdate', () => {
    const cur = audio.currentTime || 0;
    const dur = audio.duration || 0;
    if (dur > 0) {
        const percent = (cur / dur) * 100;
        bp.seek.value = percent;
        bp.timeCur.textContent = formatTime(cur);
        bp.timeTot.textContent = formatTime(dur);
        pillFill.style.width = percent + '%';
        waveSeek.value = percent;
    }
});

waveSeek.addEventListener('input', (e) => {
    if (audio.duration) {
        const percent = e.target.value;
        audio.currentTime = (percent / 100) * audio.duration;
        pillFill.style.width = percent + '%';
    }
});
bp.seek.addEventListener('input', (e) => {
    if (audio.duration) audio.currentTime = (e.target.value / 100) * audio.duration;
});

// Избранное
function updateFavUI() {
    if (!currentTrack) return;
    const isFav = favorites.some(f => f.id === currentTrack.id);
    document.getElementById('bpFavBtn').classList.toggle('is-fav', isFav);
    document.getElementById('waveFavBtn').classList.toggle('is-fav', isFav);
}

function toggleFavorite(track) {
    const idx = favorites.findIndex(f => f.id === track.id);
    if (idx === -1) favorites.unshift(track);
    else favorites.splice(idx, 1);
    localStorage.setItem('mediateka_favs_v5', JSON.stringify(favorites));
    if (currentTrack && currentTrack.id === track.id) updateFavUI();
}

document.getElementById('bpFavBtn').addEventListener('click', () => {
    if (currentTrack) {
        toggleFavorite(currentTrack);
        if (!views.favorites.classList.contains('hidden')) renderFavorites();
    }
});
document.getElementById('waveFavBtn').addEventListener('click', () => {
    if (currentTrack) {
        toggleFavorite(currentTrack);
        if (!views.favorites.classList.contains('hidden')) renderFavorites();
    }
});

function formatTime(sec) {
    const total = Math.max(0, Math.floor(Number(sec) || 0));
    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

document.addEventListener('DOMContentLoaded', () => {
    switchView('wave');
    setVolume(1.0);
});