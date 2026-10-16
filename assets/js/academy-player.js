/**
 * NATCODEV Academy Multimodal Player & Visual Engine
 * Manages video playback with synchronized voice audio, multitrack audio lectures
 * across English, Nigerian Pidgin, Hausa, Yoruba, and Igbo, interactive infographics,
 * and multimodal tab switching.
 */
(function() {
  'use strict';

  // Read Multitrack Localized Narrations from page
  let multitrackData = {};
  const dataScript = document.getElementById('lesson-multitrack-json');
  if (dataScript) {
    try {
      multitrackData = JSON.parse(dataScript.textContent || '{}');
    } catch (e) {
      multitrackData = {};
    }
  }

  function getFallbackText() {
    const lessonText = document.getElementById('lesson-body-content') || document.getElementById('lesson-content');
    return lessonText ? lessonText.textContent.replace(/\s+/g, ' ').trim() : '';
  }

  function getTrackScript(langKey) {
    if (multitrackData[langKey] && multitrackData[langKey].script) {
      return multitrackData[langKey].script;
    }
    if (multitrackData['en'] && multitrackData['en'].script) {
      return multitrackData['en'].script;
    }
    return getFallbackText();
  }

  // Pre-load and cache voices
  let cachedVoices = [];
  function populateVoices() {
    if (!('speechSynthesis' in window)) return;
    const v = window.speechSynthesis.getVoices();
    if (v && v.length) {
      cachedVoices = v;
    }
  }
  populateVoices();
  if ('speechSynthesis' in window && window.speechSynthesis.onvoiceschanged !== undefined) {
    window.speechSynthesis.onvoiceschanged = populateVoices;
  }

  /**
   * Finds the best voice and ensures utterance language strictly matches
   * an installed voice language to avoid Chromium "language-unavailable" errors.
   */
  function findVoiceAndLang(langCode) {
    populateVoices();
    const voices = cachedVoices.length ? cachedVoices : (('speechSynthesis' in window) ? window.speechSynthesis.getVoices() || [] : []);

    let matchedVoice = null;

    if (langCode === 'pcm') {
      matchedVoice = voices.find(function(v) { return v.lang === 'en-NG' || v.lang === 'en_NG'; })
                  || voices.find(function(v) { return v.lang.startsWith('en-GB') || v.lang.startsWith('en_GB'); })
                  || voices.find(function(v) { return v.lang.startsWith('en'); });
    } else if (langCode === 'ha') {
      matchedVoice = voices.find(function(v) { return v.lang.startsWith('ha'); })
                  || voices.find(function(v) { return v.lang === 'en-NG' || v.lang === 'en_NG'; })
                  || voices.find(function(v) { return v.lang.startsWith('en'); });
    } else if (langCode === 'yo') {
      matchedVoice = voices.find(function(v) { return v.lang.startsWith('yo'); })
                  || voices.find(function(v) { return v.lang === 'en-NG' || v.lang === 'en_NG'; })
                  || voices.find(function(v) { return v.lang.startsWith('en'); });
    } else if (langCode === 'ig') {
      matchedVoice = voices.find(function(v) { return v.lang.startsWith('ig'); })
                  || voices.find(function(v) { return v.lang === 'en-NG' || v.lang === 'en_NG'; })
                  || voices.find(function(v) { return v.lang.startsWith('en'); });
    } else {
      matchedVoice = voices.find(function(v) { return v.lang === 'en-NG' || v.lang === 'en_NG'; })
                  || voices.find(function(v) { return v.lang.startsWith('en-GB') || v.lang.startsWith('en_GB'); })
                  || voices.find(function(v) { return v.lang.startsWith('en'); });
    }

    if (!matchedVoice && voices.length) {
      matchedVoice = voices[0];
    }

    // Crucial: Use the voice's own supported BCP-47 language tag so the browser speech engine never rejects it
    let safeLang = matchedVoice ? matchedVoice.lang : (langCode === 'pcm' ? 'en-NG' : (langCode === 'ha' || langCode === 'yo' || langCode === 'ig' ? 'en-US' : 'en-US'));

    return {
      voice: matchedVoice,
      lang: safeLang
    };
  }

  // 1. Multimodal Tab Switching
  const mediaTabs = document.querySelectorAll('.media-tab');
  const mediaPanels = document.querySelectorAll('.media-panel');

  if (mediaTabs.length > 0) {
    mediaTabs.forEach(function(tab) {
      tab.addEventListener('click', function() {
        const target = this.getAttribute('data-tab');
        mediaTabs.forEach(function(t) { t.classList.remove('active'); });
        mediaPanels.forEach(function(p) { p.classList.remove('active'); });

        this.classList.add('active');
        const activePanel = document.getElementById('panel-' + target);
        if (activePanel) {
          activePanel.classList.add('active');
        }

        // When switching to the video tab, synchronize video voice track and auto-stream sound
        if (target === 'video') {
          const videoTrackSelect = document.getElementById('video-audio-track-select');
          const audioTrackSelect = document.getElementById('audio-track-select');
          if (videoTrackSelect && audioTrackSelect) {
            videoTrackSelect.value = audioTrackSelect.value || 'en';
          }
          if (typeof startVideoNarration === 'function' && !isVideoNarrating) {
            startVideoNarration();
          }
        } else if (target !== 'video' && typeof stopVideoNarration === 'function') {
          stopVideoNarration();
        }
      });
    });
  }

  // 2. Multitrack Audio Player
  const audioCard = document.querySelector('.audio-player-card');
  let audio = new Audio();
  let isPlaying = false;
  let playbackRates = [1.0, 1.25, 1.5, 0.75];
  let currentRateIdx = 0;
  let currentLang = 'en';
  let isSynthActive = false;
  let synthTimer = null;
  let synthElapsedSec = 0;
  let synthDurationSec = 60;

  let playBtn = null;
  let playIcon = null;
  let scrubBar = null;
  let scrubFill = null;
  let curTimeEl = null;
  let durTimeEl = null;
  let speedBtn = null;
  let trackSelect = null;
  let downloadLink = null;
  let pulseIndicator = null;
  let skipBackBtn = null;
  let skipFwdBtn = null;
  let activeLabel = null;

  function formatTime(seconds) {
    if (isNaN(seconds) || seconds < 0) return '00:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
  }

  function setPlayingUI(playing) {
    isPlaying = playing;
    if (playIcon) {
      playIcon.className = playing ? 'fas fa-pause' : 'fas fa-play';
    }
    if (pulseIndicator) {
      pulseIndicator.style.animationPlayState = playing ? 'running' : 'paused';
    }
  }

  function stopAllAudio() {
    audio.pause();
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
    }
    if (synthTimer) {
      clearInterval(synthTimer);
      synthTimer = null;
    }
    isSynthActive = false;
    setPlayingUI(false);
  }

  function playLocalizedSynthesis(startSec) {
    if (!('speechSynthesis' in window)) {
      alert('Audio playback and browser speech synthesis are unavailable in this environment.');
      return;
    }
    stopAllAudio();

    const scriptText = getTrackScript(currentLang);
    if (!scriptText) return;

    const words = scriptText.split(/\s+/);
    const rate = playbackRates[currentRateIdx];
    synthDurationSec = Math.max(15, Math.round((words.length / 130) * 60 / rate));
    if (durTimeEl) durTimeEl.textContent = formatTime(synthDurationSec);

    synthElapsedSec = Math.max(0, Math.min(synthDurationSec, startSec || 0));

    // Calculate starting position in text if seeking
    let textToSpeak = scriptText;
    if (synthElapsedSec > 0 && synthDurationSec > 0) {
      const charStart = Math.floor((synthElapsedSec / synthDurationSec) * scriptText.length);
      const nearSpace = scriptText.indexOf(' ', charStart);
      if (nearSpace > 0 && nearSpace < scriptText.length - 10) {
        textToSpeak = scriptText.substring(nearSpace).trim();
      }
    }

    const utter = new SpeechSynthesisUtterance(textToSpeak);
    const matched = findVoiceAndLang(currentLang);
    if (matched.voice) {
      utter.voice = matched.voice;
    }
    utter.lang = matched.lang;

    // Language-specific voice inflections
    if (currentLang === 'pcm') {
      utter.rate = rate * 0.90;
      utter.pitch = 1.08;
    } else if (currentLang === 'ha') {
      utter.rate = rate * 0.88;
      utter.pitch = 1.0;
    } else if (currentLang === 'yo') {
      utter.rate = rate * 0.88;
      utter.pitch = 1.04;
    } else if (currentLang === 'ig') {
      utter.rate = rate * 0.88;
      utter.pitch = 1.02;
    } else {
      utter.rate = rate * 0.96;
      utter.pitch = 1.0;
    }

    utter.onend = function() {
      stopAllAudio();
      synthElapsedSec = 0;
      if (scrubFill) scrubFill.style.width = '0%';
      if (curTimeEl) curTimeEl.textContent = '00:00';
    };
    utter.onerror = function(e) {
      stopAllAudio();
    };

    window.speechSynthesis.speak(utter);
    isSynthActive = true;
    setPlayingUI(true);

    synthTimer = setInterval(function() {
      if (!isSynthActive) {
        clearInterval(synthTimer);
        return;
      }
      synthElapsedSec += 0.25;
      if (synthElapsedSec >= synthDurationSec) {
        synthElapsedSec = synthDurationSec;
        stopAllAudio();
      }
      const pct = Math.min(100, (synthElapsedSec / synthDurationSec) * 100);
      if (scrubFill) scrubFill.style.width = pct + '%';
      if (curTimeEl) curTimeEl.textContent = formatTime(synthElapsedSec);
    }, 250);
  }

  function togglePlay() {
    if (isPlaying) {
      stopAllAudio();
      return;
    }

    // Always prefer instant, clear localized speech synthesis for all multitrack languages
    playLocalizedSynthesis(synthElapsedSec);
  }

  function loadTrack(url, lang) {
    const wasPlaying = isPlaying;
    stopAllAudio();
    currentLang = lang || 'en';

    if (activeLabel && trackSelect) {
      const opt = trackSelect.options[trackSelect.selectedIndex];
      activeLabel.textContent = 'Voice track: ' + (opt ? opt.textContent : 'Active');
    }

    // Also sync the video player track dropdown
    const videoTrackSelect = document.getElementById('video-audio-track-select');
    if (videoTrackSelect && videoTrackSelect.value !== currentLang) {
      videoTrackSelect.value = currentLang;
    }

    synthElapsedSec = 0;
    if (scrubFill) scrubFill.style.width = '0%';
    if (curTimeEl) curTimeEl.textContent = '00:00';

    const scriptText = getTrackScript(currentLang);
    const wordCount = scriptText.split(/\s+/).length;
    synthDurationSec = Math.max(15, Math.round((wordCount / 130) * 60));
    if (durTimeEl) durTimeEl.textContent = formatTime(synthDurationSec);

    if (wasPlaying) {
      togglePlay();
    }
  }

  if (audioCard) {
    playBtn = audioCard.querySelector('#audio-play-btn');
    playIcon = playBtn ? playBtn.querySelector('i') : null;
    scrubBar = audioCard.querySelector('#audio-scrub-bar');
    scrubFill = audioCard.querySelector('#audio-scrub-fill');
    curTimeEl = audioCard.querySelector('#audio-cur-time');
    durTimeEl = audioCard.querySelector('#audio-dur-time');
    speedBtn = audioCard.querySelector('#audio-speed-btn');
    trackSelect = audioCard.querySelector('#audio-track-select');
    downloadLink = audioCard.querySelector('#audio-download-link');
    pulseIndicator = audioCard.querySelector('.audio-pulse-indicator');
    skipBackBtn = audioCard.querySelector('#audio-skip-back');
    skipFwdBtn = audioCard.querySelector('#audio-skip-fwd');
    activeLabel = audioCard.querySelector('#audio-active-track-label');

    if (trackSelect) {
      trackSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const trackUrl = opt.getAttribute('data-url') || '';
        const lang = opt.getAttribute('data-lang') || opt.value || 'en';
        loadTrack(trackUrl, lang);
      });

      if (trackSelect.options.length > 0) {
        const initOpt = trackSelect.options[trackSelect.selectedIndex];
        const initUrl = initOpt.getAttribute('data-url') || '';
        const initLang = initOpt.getAttribute('data-lang') || initOpt.value || 'en';
        loadTrack(initUrl, initLang);
      }
    }

    if (playBtn) {
      playBtn.addEventListener('click', togglePlay);
    }

    if (skipBackBtn) {
      skipBackBtn.addEventListener('click', function() {
        if (isSynthActive) {
          synthElapsedSec = Math.max(0, synthElapsedSec - 15);
          playLocalizedSynthesis(synthElapsedSec);
        }
      });
    }

    if (skipFwdBtn) {
      skipFwdBtn.addEventListener('click', function() {
        if (isSynthActive) {
          synthElapsedSec = Math.min(synthDurationSec - 2, synthElapsedSec + 15);
          playLocalizedSynthesis(synthElapsedSec);
        }
      });
    }

    if (speedBtn) {
      speedBtn.addEventListener('click', function() {
        currentRateIdx = (currentRateIdx + 1) % playbackRates.length;
        const rate = playbackRates[currentRateIdx];
        speedBtn.textContent = rate + 'x';
        if (isSynthActive) {
          playLocalizedSynthesis(synthElapsedSec);
        }
      });
    }

    if (scrubBar) {
      scrubBar.addEventListener('click', function(e) {
        const rect = scrubBar.getBoundingClientRect();
        const clickPos = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        synthElapsedSec = clickPos * synthDurationSec;
        if (isSynthActive) {
          playLocalizedSynthesis(synthElapsedSec);
        } else {
          if (curTimeEl) curTimeEl.textContent = formatTime(synthElapsedSec);
          if (scrubFill) scrubFill.style.width = (clickPos * 100) + '%';
        }
      });
    }
  }

  // 3. HTML5 Video Player & Synchronized Video Narration
  const videoWrapper = document.querySelector('.video-player-wrapper');
  const videoEl = videoWrapper ? videoWrapper.querySelector('video') : null;
  const videoNarrationBtn = document.getElementById('video-narration-btn');
  const videoTrackSelect = document.getElementById('video-audio-track-select');
  let isVideoNarrating = false;

  function stopVideoNarration() {
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
    }
    isVideoNarrating = false;
    if (videoNarrationBtn) {
      videoNarrationBtn.className = 'btn btn-p btn-s';
      videoNarrationBtn.innerHTML = '<i class="fas fa-volume-high"></i> <span id="video-narration-label">Play Video Audio</span>';
    }
  }

  function startVideoNarration() {
    if (!('speechSynthesis' in window)) {
      alert('Browser speech synthesis is not supported in this browser.');
      return;
    }
    stopAllAudio();
    stopVideoNarration();

    const lang = videoTrackSelect ? videoTrackSelect.value : (currentLang || 'en');
    const script = getTrackScript(lang);
    if (!script) return;

    // Sync audio player language dropdown as well
    if (trackSelect && trackSelect.value !== lang) {
      trackSelect.value = lang;
      if (activeLabel) {
        const opt = trackSelect.options[trackSelect.selectedIndex];
        activeLabel.textContent = 'Voice track: ' + (opt ? opt.textContent : 'Active');
      }
    }
    currentLang = lang;

    const utter = new SpeechSynthesisUtterance(script);
    const matched = findVoiceAndLang(lang);
    if (matched.voice) {
      utter.voice = matched.voice;
    }
    utter.lang = matched.lang;

    if (lang === 'pcm') {
      utter.rate = 0.90;
      utter.pitch = 1.08;
    } else if (lang === 'ha') {
      utter.rate = 0.88;
      utter.pitch = 1.0;
    } else if (lang === 'yo') {
      utter.rate = 0.88;
      utter.pitch = 1.04;
    } else if (lang === 'ig') {
      utter.rate = 0.88;
      utter.pitch = 1.02;
    } else {
      utter.rate = 0.95;
      utter.pitch = 1.0;
    }

    utter.onend = function() {
      stopVideoNarration();
    };
    utter.onerror = function() {
      stopVideoNarration();
    };

    window.speechSynthesis.speak(utter);
    isVideoNarrating = true;
    if (videoNarrationBtn) {
      videoNarrationBtn.className = 'btn btn-o btn-s';
      const langNames = { en: 'English', pcm: 'Pidgin', ha: 'Hausa', yo: 'Yoruba', ig: 'Igbo' };
      const trackName = langNames[lang] || 'Audio';
      videoNarrationBtn.innerHTML = '<i class="fas fa-volume-xmark" style="color:#e74c3c"></i> <span id="video-narration-label">Pause ' + trackName + ' Audio</span>';
    }
  }

  if (videoNarrationBtn) {
    videoNarrationBtn.addEventListener('click', function() {
      if (isVideoNarrating) {
        stopVideoNarration();
      } else {
        startVideoNarration();
      }
    });
  }

  if (videoTrackSelect) {
    videoTrackSelect.addEventListener('change', function() {
      const selectedLang = this.value;
      if (trackSelect && trackSelect.value !== selectedLang) {
        trackSelect.value = selectedLang;
        if (activeLabel) {
          const opt = trackSelect.options[trackSelect.selectedIndex];
          activeLabel.textContent = 'Voice track: ' + (opt ? opt.textContent : 'Active');
        }
      }
      currentLang = selectedLang;
      if (isVideoNarrating) {
        startVideoNarration();
      }
    });
  }

  if (videoEl) {
    document.querySelectorAll('.video-speed-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const speed = parseFloat(this.getAttribute('data-speed') || '1');
        videoEl.playbackRate = speed;
        document.querySelectorAll('.video-speed-btn').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
      });
    });

    videoEl.addEventListener('play', function() {
      if (!isVideoNarrating) {
        startVideoNarration();
      }
    });

    videoEl.addEventListener('pause', function() {
      if (isVideoNarrating) stopVideoNarration();
    });
  }

  // 4. Interactive Infographics Lightbox & Inspection
  const modal = document.createElement('div');
  modal.className = 'infographic-modal';
  modal.innerHTML = '<div class="infographic-modal-content"><button type="button" class="infographic-modal-close">&times;</button><div class="infographic-modal-body"></div></div>';
  document.body.appendChild(modal);

  const modalBody = modal.querySelector('.infographic-modal-body');
  const modalClose = modal.querySelector('.infographic-modal-close');

  function closeModal() {
    modal.classList.remove('active');
    if (modalBody) modalBody.innerHTML = '';
  }

  if (modalClose) {
    modalClose.addEventListener('click', closeModal);
  }
  modal.addEventListener('click', function(e) {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
  });

  document.querySelectorAll('[data-action="inspect-infographic"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const card = this.closest('.infographic-card');
      const svg = card ? card.querySelector('.infographic-viewport svg') : null;
      if (svg && modalBody) {
        modalBody.innerHTML = svg.outerHTML;
        modal.classList.add('active');
      }
    });
  });

})();
