/**
 * StoryPlayer - A customizable story viewer component
 * @version 4.0.0
 *
 * هر استوری چند آیتم (تصویر یا ویدیو) دارد؛ هر آیتم عنوان، توضیحات و لینک اختیاری دارد.
 * stories: [{ user, avatar, items: [{ type: 'image'|'video', url, title, description, duration, link }] }]
 * (سازگار با قالب قدیمی: { type, url, duration, link } بدون items)
 * @class
 */
class StoryPlayer {
    /**
     * @param {string} containerId - The ID of the container element.
     * @param {Array} stories - An array of story objects.
     */
    constructor(containerId, stories) {
        this.container = document.getElementById(containerId);
        this.stories = (stories || []).map(story => ({
            ...story,
            items: Array.isArray(story.items) && story.items.length
                ? story.items
                : [{ type: story.type, url: story.url, duration: story.duration, link: story.link }],
        })).filter(story => story.items.some(item => item.url));
        this.currentStoryIndex = 0;
        this.currentItemIndex = 0;
        this.timer = null;
        this.startedAt = 0;
        this.remaining = 0;
        this.isPaused = false;
        this.touchStartX = 0;
        this.touchEndX = 0;
        this.onKeyDown = this.handleKey.bind(this);

        if (this.container) this.init();
    }

    init() {
        this.renderStories();
    }

    escape(value) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
        return String(value ?? '').replace(/[&<>"']/g, ch => map[ch]);
    }

    /**
     * Render the story avatars in the container.
     */
    renderStories() {
        this.container.classList.add('flex', 'overflow-x-auto', 'gap-4', 'p-4', 'no-scrollbar');

        this.container.innerHTML = this.stories.map((story, index) => `
            <div class="story" data-index="${index}">
                <div class="story-avatar">
                    <img src="${this.escape(story.avatar || '')}" alt="${this.escape(story.user)}" onerror="this.src='https://picsum.photos/70'">
                </div>
                <div class="story-username dark:!text-white">${this.escape(story.user)}</div>
            </div>
        `).join('');

        this.container.querySelectorAll('.story').forEach(story => {
            story.addEventListener('click', () => {
                this.currentStoryIndex = parseInt(story.dataset.index);
                this.currentItemIndex = 0;
                this.openStory();
            });
        });
    }

    get story() {
        return this.stories[this.currentStoryIndex];
    }

    get item() {
        return this.story.items[this.currentItemIndex];
    }

    openStory() {
        this.createModal();
        this.loadItem();
        document.addEventListener('keydown', this.onKeyDown);
    }

    createModal() {
        this.modal = document.createElement('div');
        this.modal.className = 'story-modal';
        this.modal.innerHTML = `
            <div class="progress-container"></div>
            <div class="story-header">
                <img class="story-header-avatar" alt="">
                <span class="story-header-name"></span>
            </div>
            <div class="story-content">
                <button class="close-button" aria-label="بستن">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <button class="nav-button prev-button" aria-label="قبلی">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
                <div class="media-container">
                    <img class="story-media" alt="">
                    <video class="video-media" playsinline></video>
                    <div class="story-caption" style="display: none;">
                        <div class="story-caption-title"></div>
                        <div class="story-caption-text"></div>
                    </div>
                    <a href="#" class="story-link" target="_blank" rel="noopener" style="display: none;">مشاهده لینک</a>
                </div>
                <button class="nav-button next-button" aria-label="بعدی">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
            </div>
        `;
        document.body.appendChild(this.modal);
        this.setupModalEventListeners();
    }

    /** نوارهای پیشرفت: یک بخش برای هر آیتم استوری فعلی */
    renderProgress() {
        const container = this.modal.querySelector('.progress-container');
        container.innerHTML = this.story.items.map((_, i) => `
            <div class="progress-segment"><div class="progress-fill" style="width: ${i < this.currentItemIndex ? 100 : 0}%"></div></div>
        `).join('');
    }

    currentFill() {
        return this.modal.querySelectorAll('.progress-fill')[this.currentItemIndex];
    }

    /**
     * Load the current item into the modal.
     */
    loadItem() {
        const story = this.story;
        const item = this.item;
        this.modal.style.display = 'block';
        this.modal.classList.remove('paused');
        this.isPaused = false;

        this.modal.querySelector('.story-header-avatar').src = story.avatar || '';
        this.modal.querySelector('.story-header-name').textContent = story.user || '';
        this.renderProgress();

        const storyLink = this.modal.querySelector('.story-link');
        storyLink.style.display = item.link ? 'block' : 'none';
        storyLink.href = item.link || '#';

        const caption = this.modal.querySelector('.story-caption');
        const title = (item.title || '').trim();
        const text = (item.description || '').trim();
        caption.querySelector('.story-caption-title').textContent = title;
        caption.querySelector('.story-caption-text').textContent = text;
        caption.style.display = title || text ? 'block' : 'none';
        caption.classList.toggle('with-link', !!item.link);

        if (item.type === 'video') {
            this.setupVideo(item);
        } else {
            this.setupImage(item);
        }

        const avatar = this.container.querySelectorAll('.story-avatar')[this.currentStoryIndex];
        if (avatar && this.currentItemIndex === story.items.length - 1) avatar.classList.add('viewed');
    }

    setupVideo(item) {
        const videoMedia = this.modal.querySelector('.video-media');
        const imageMedia = this.modal.querySelector('.story-media');
        imageMedia.style.display = 'none';
        videoMedia.style.display = 'block';
        videoMedia.src = item.url;

        videoMedia.ontimeupdate = () => {
            if (!videoMedia.duration) return;
            const fill = this.currentFill();
            if (fill) {
                fill.style.transition = 'none';
                fill.style.width = `${(videoMedia.currentTime / videoMedia.duration) * 100}%`;
            }
        };
        videoMedia.onended = () => this.next();
        videoMedia.onerror = () => this.next();

        const playing = videoMedia.play();
        if (playing && playing.catch) {
            // پخش خودکار با صدا ممکن است مسدود شود؛ بی‌صدا دوباره تلاش می‌شود
            playing.catch(() => { videoMedia.muted = true; videoMedia.play().catch(() => {}); });
        }
    }

    setupImage(item) {
        const videoMedia = this.modal.querySelector('.video-media');
        const imageMedia = this.modal.querySelector('.story-media');
        videoMedia.style.display = 'none';
        videoMedia.removeAttribute('src');
        imageMedia.style.display = 'block';
        imageMedia.src = item.url;
        this.startTimer(Number(item.duration) || 7000);
    }

    startTimer(duration) {
        const fill = this.currentFill();
        clearTimeout(this.timer);
        this.startedAt = Date.now();
        this.remaining = duration;

        if (fill) {
            void fill.offsetWidth;
            fill.style.transition = `width ${duration}ms linear`;
            fill.style.width = '100%';
        }

        this.timer = setTimeout(() => {
            if (!this.isPaused) this.next();
        }, duration);
    }

    /** آیتم بعدی، سپس استوری بعدی */
    next() {
        this.resetMedia();

        if (this.currentItemIndex < this.story.items.length - 1) {
            this.currentItemIndex++;
        } else if (this.currentStoryIndex < this.stories.length - 1) {
            const avatar = this.container.querySelectorAll('.story-avatar')[this.currentStoryIndex];
            if (avatar) avatar.classList.add('viewed');
            this.currentStoryIndex++;
            this.currentItemIndex = 0;
        } else {
            this.closeStory();
            return;
        }

        this.loadItem();
    }

    prev() {
        this.resetMedia();

        if (this.currentItemIndex > 0) {
            this.currentItemIndex--;
        } else if (this.currentStoryIndex > 0) {
            this.currentStoryIndex--;
            this.currentItemIndex = this.story.items.length - 1;
        }

        this.loadItem();
    }

    // نام‌های قبلی برای سازگاری
    nextStory() { this.next(); }
    prevStory() { this.prev(); }

    resetMedia() {
        const videoMedia = this.modal.querySelector('.video-media');
        videoMedia.pause();
        videoMedia.ontimeupdate = null;
        videoMedia.onended = null;
        videoMedia.onerror = null;
        clearTimeout(this.timer);
        this.isPaused = false;
    }

    closeStory() {
        this.resetMedia();
        document.removeEventListener('keydown', this.onKeyDown);
        this.modal.remove();
    }

    handleKey(e) {
        if (e.key === 'Escape') this.closeStory();
        // صفحه راست‌به‌چپ: فلش چپ = بعدی
        if (e.key === 'ArrowLeft') this.next();
        if (e.key === 'ArrowRight') this.prev();
        if (e.key === ' ') {
            e.preventDefault();
            this.togglePause();
        }
    }

    setupModalEventListeners() {
        const closeBtn = this.modal.querySelector('.close-button');
        const prevBtn = this.modal.querySelector('.prev-button');
        const nextBtn = this.modal.querySelector('.next-button');
        const mediaContainer = this.modal.querySelector('.media-container');

        closeBtn.addEventListener('click', () => this.closeStory());
        prevBtn.addEventListener('click', () => this.prev());
        nextBtn.addEventListener('click', () => this.next());

        mediaContainer.addEventListener('click', (e) => {
            if (e.target.closest('.story-link')) return;
            this.togglePause();
        });

        mediaContainer.addEventListener('touchstart', (e) => {
            this.touchStartX = e.changedTouches[0].clientX;
        }, { passive: true });

        mediaContainer.addEventListener('touchend', (e) => {
            this.touchEndX = e.changedTouches[0].clientX;
            this.handleSwipe();
        });
    }

    handleSwipe() {
        const swipeThreshold = 50;
        const swipeDistance = this.touchEndX - this.touchStartX;

        if (swipeDistance > swipeThreshold) {
            this.next();
        } else if (swipeDistance < -swipeThreshold) {
            this.prev();
        }
    }

    togglePause() {
        this.isPaused = !this.isPaused;
        const videoMedia = this.modal.querySelector('.video-media');
        this.modal.classList.toggle('paused', this.isPaused);

        if (this.item.type === 'video') {
            this.isPaused ? videoMedia.pause() : videoMedia.play().catch(() => {});
            return;
        }

        const fill = this.currentFill();

        if (this.isPaused) {
            clearTimeout(this.timer);
            this.remaining = Math.max(0, this.remaining - (Date.now() - this.startedAt));
            if (fill) {
                fill.style.width = getComputedStyle(fill).width;
                fill.style.transition = 'none';
            }
        } else {
            this.startTimer(this.remaining);
        }
    }
}

/**
 * ScrollManager - Manages smooth drag scrolling for horizontal containers
 * @class
 */
class ScrollManager {
    /**
     * Constructor to initialize the scroll manager.
     * @param {string} containerId - The ID of the container element.
     */
    constructor(containerId) {
        this.slider = document.getElementById(containerId);
        if (!this.slider) return;

        this.isDown = false;
        this.startX = 0;
        this.scrollLeft = 0;
        this.velocity = 0;
        this.rafId = null;
        this.lastX = 0;
        this.lastTime = 0;

        this.init();
    }

    /**
     * Initialize the scroll manager by setting up event listeners.
     */
    init() {
        this.setupEventListeners();
    }

    /**
     * Set up event listeners for smooth drag scrolling.
     */
    setupEventListeners() {
        this.slider.addEventListener('mousedown', (e) => this.onMouseDown(e));
        this.slider.addEventListener('mouseleave', (e) => this.onMouseLeave(e));
        this.slider.addEventListener('mouseup', (e) => this.onMouseUp(e));
        this.slider.addEventListener('mousemove', (e) => this.onMouseMove(e));
    }

    /**
     * Handle mouse down event.
     * @param {Event} e - The mouse event.
     */
    onMouseDown(e) {
        this.isDown = true;
        this.slider.style.cursor = 'grabbing';
        this.slider.style.scrollBehavior = 'auto';

        this.startX = e.pageX - this.slider.offsetLeft;
        this.scrollLeft = this.slider.scrollLeft;

        cancelAnimationFrame(this.rafId);
        this.velocity = 0;
        this.lastTime = Date.now();
        this.lastX = e.pageX;
    }

    /**
     * Handle mouse leave event.
     * @param {Event} e - The mouse event.
     */
    onMouseLeave(e) {
        if (!this.isDown) return;
        this.isDown = false;
        this.slider.style.cursor = 'grab';
        this.startMomentum();
    }

    /**
     * Handle mouse up event.
     * @param {Event} e - The mouse event.
     */
    onMouseUp(e) {
        this.isDown = false;
        this.slider.style.cursor = 'grab';
        this.startMomentum();
    }

    /**
     * Handle mouse move event.
     * @param {Event} e - The mouse event.
     */
    onMouseMove(e) {
        if (!this.isDown) return;
        e.preventDefault();

        const x = e.pageX - this.slider.offsetLeft;
        const walk = (x - this.startX);
        this.slider.scrollLeft = this.scrollLeft - walk;

        // Calculate instantaneous velocity for inertia
        const currentTime = Date.now();
        const deltaTime = currentTime - this.lastTime;
        if (deltaTime > 0) {
            this.velocity = (this.lastX - e.pageX) / deltaTime * 15;
        }
        this.lastTime = currentTime;
        this.lastX = e.pageX;
    }

    /**
     * Start momentum-based scrolling.
     */
    startMomentum() {
        const momentum = () => {
            if (Math.abs(this.velocity) > 0.1) {
                this.slider.scrollLeft += this.velocity;
                this.velocity *= 0.95;
                this.rafId = requestAnimationFrame(momentum);
            } else {
                cancelAnimationFrame(this.rafId);
            }
        };
        momentum();
    }

    /**
     * Destroy the scroll manager and remove event listeners.
     */
    destroy() {
        this.slider.removeEventListener('mousedown', this.onMouseDown);
        this.slider.removeEventListener('mouseleave', this.onMouseLeave);
        this.slider.removeEventListener('mouseup', this.onMouseUp);
        this.slider.removeEventListener('mousemove', this.onMouseMove);
        cancelAnimationFrame(this.rafId);
    }
}

// Auto-initialize ScrollManager when DOM is ready
(function() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new ScrollManager('stories-container');
        });
    } else {
        new ScrollManager('stories-container');
    }
})();