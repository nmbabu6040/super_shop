import "@fontsource-variable/inter";
import "@fontsource/noto-sans-bengali/400.css";
import "@fontsource/noto-sans-bengali/500.css";
import "@fontsource/noto-sans-bengali/600.css";
import "@fontsource/noto-sans-bengali/700.css";

import Alpine from "alpinejs";
import Swal from "sweetalert2";
import { requestJson } from "./http";

window.Alpine = Alpine;
window.requestJson = requestJson;

// Subtle 3D tilt for the authentication card. It stops while a field has focus
// (so typing is never distracting) and is disabled for users who prefer reduced motion.
Alpine.data("tiltCard", () => ({
    rotateX: 0,
    rotateY: 0,
    isFrozen: false,
    prefersReducedMotion: window.matchMedia("(prefers-reduced-motion: reduce)")
        .matches,

    move(event) {
        if (this.prefersReducedMotion || this.isFrozen) {
            return;
        }

        const bounds = this.$el.getBoundingClientRect();
        const offsetX = (event.clientX - bounds.left) / bounds.width - 0.5;
        const offsetY = (event.clientY - bounds.top) / bounds.height - 0.5;

        this.rotateY = offsetX * 6;
        this.rotateX = offsetY * -6;
    },

    reset() {
        this.rotateX = 0;
        this.rotateY = 0;
    },

    freeze() {
        this.isFrozen = true;
        this.reset();
    },

    unfreeze() {
        this.isFrozen = false;
    },

    get style() {
        return `transform: perspective(1000px) rotateX(${this.rotateX}deg) rotateY(${this.rotateY}deg)`;
    },
}));

// Notification bell of the admin top bar. It polls a small JSON feed once a minute
// (works on shared hosting too; a WebSocket upgrade can replace polling later).
Alpine.data("notificationBell", (config) => ({
    open: false,
    unread: config.unread,
    items: [],
    loading: false,
    pollTimer: null,

    init() {
        this.pollTimer = window.setInterval(() => this.refresh(), 60000);
    },

    destroy() {
        window.clearInterval(this.pollTimer);
    },

    async refresh() {
        try {
            const feed = await window.requestJson(config.feedUrl);
            this.unread = feed.unread_count;
            this.items = feed.items;
        } catch (requestError) {
            // Polling must never break the page.
        }
    },

    async toggle() {
        this.open = !this.open;

        if (this.open) {
            this.loading = true;
            await this.refresh();
            this.loading = false;
        }
    },

    async openItem(item) {
        try {
            await window.requestJson(
                config.readUrlTemplate.replace("__ID__", item.id),
                { method: "POST" },
            );
        } catch (requestError) {
            // The link still works even if marking as read failed.
        }

        if (item.url) {
            window.location.href = item.url;

            return;
        }

        await this.refresh();
    },

    async markAllRead() {
        try {
            await window.requestJson(config.readAllUrl, { method: "POST" });
        } catch (requestError) {
            // Ignored: the next poll shows the real state.
        }

        await this.refresh();
    },
}));

// Confirmation dialog for destructive actions. Usage on a form:
// <form data-confirm="Text" data-confirm-title="Title" data-confirm-yes="Yes" data-confirm-no="No">
document.addEventListener("submit", (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
        return;
    }

    event.preventDefault();

    const rootStyle = getComputedStyle(document.documentElement);

    Swal.fire({
        title: form.dataset.confirmTitle ?? "",
        text: form.dataset.confirm,
        icon: "warning",
        showCancelButton: true,
        focusCancel: true,
        confirmButtonText: form.dataset.confirmYes ?? "OK",
        cancelButtonText: form.dataset.confirmNo ?? "Cancel",
        confirmButtonColor:
            rootStyle.getPropertyValue("--brand-600").trim() || "#0f9179",
        background:
            rootStyle.getPropertyValue("--surface-raised").trim() || "#ffffff",
        color: rootStyle.getPropertyValue("--ink").trim() || "#0f172a",
    }).then((result) => {
        if (result.isConfirmed) {
            // form.submit() does not fire a submit event, so this handler is not triggered again.
            form.submit();
        }
    });
});

Alpine.start();
