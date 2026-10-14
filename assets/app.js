"use strict";

(() => {
    const scene = document.querySelector(".ambient-scene[data-backgrounds]");
    const layers = scene?.querySelectorAll("[data-gradient-layer]");
    if (layers?.length !== 2) return;
    const images = Array.from(layers, (layer) => layer.querySelector("[data-background-image]"));
    if (images.some((image) => !image)) return;
    let backgrounds;
    try {
        const values = JSON.parse(scene.dataset.backgrounds);
        if (!Array.isArray(values)) return;
        backgrounds = [...new Set(values
            .filter((value) => typeof value === "string" && value.trim() !== "")
            .map((value) => new URL(value, document.baseURI))
            .filter((url) => url.origin === window.location.origin)
            .map((url) => url.href))];
    } catch {
        return;
    }
    if (backgrounds.length === 0) return;
    const motion = window.matchMedia("(prefers-reduced-motion: reduce)");
    const initial = backgrounds[Math.floor(Math.random() * backgrounds.length)];
    let current = images[0].src;
    let activeLayer = 0;
    let timer = null;
    let pending = null;
    let initialPending = true;
    let pageActive = true;
    layers[0].dataset.visible = "true";
    layers[1].dataset.visible = "false";

    images.forEach((image) => {
        image.addEventListener("error", () => { image.hidden = true; });
        image.addEventListener("load", () => { image.hidden = false; });
        if (image.complete && image.naturalWidth === 0) image.hidden = true;
    });
    const cancelLoad = () => {
        if (!pending) return;
        pending.loader.onload = null;
        pending.loader.onerror = null;
        pending = null;
    };
    const canFinish = (request) => {
        if (pending !== request) return false;
        if (!pageActive || document.hidden || (!request.initial && motion.matches)) {
            updateTimer();
            return false;
        }
        return true;
    };
    const finishLoad = (request, success) => {
        if (!canFinish(request)) return;
        cancelLoad();
        if (request.initial) initialPending = false;
        if (success) {
            images[request.layer].hidden = false;
            layers[request.layer].dataset.visible = "true";
            layers[activeLayer].dataset.visible = "false";
            activeLayer = request.layer;
            current = request.url;
        }
        updateTimer();
    };
    const showBackground = (url, isInitial = false) => {
        if (url === current) {
            if (isInitial) initialPending = false;
            updateTimer();
            return;
        }
        const request = { loader: new Image(), url, initial: isInitial, layer: 1 - activeLayer };
        pending = request;
        request.loader.onerror = () => finishLoad(request, false);
        request.loader.onload = () => {
            if (!canFinish(request)) return;
            if (request.loader.naturalWidth === 0) {
                finishLoad(request, false);
                return;
            }
            const image = images[request.layer];
            image.src = url;
            if (typeof image.decode === "function") {
                image.decode().then(() => finishLoad(request, true), () => finishLoad(request, false));
            } else {
                finishLoad(request, true);
            }
        };
        request.loader.src = url;
    };
    const updateTimer = () => {
        if (!pageActive || document.hidden || motion.matches) {
            if (timer !== null) window.clearTimeout(timer);
            timer = null;
            if (!pageActive || document.hidden || !pending?.initial) cancelLoad();
        }
        if (!pageActive || document.hidden) return;
        if (initialPending) {
            if (!pending) showBackground(initial, true);
            return;
        }
        if (motion.matches || backgrounds.length < 2 || pending || timer !== null) return;
        timer = window.setTimeout(() => {
            timer = null;
            const choices = backgrounds.filter((url) => url !== current);
            showBackground(choices[Math.floor(Math.random() * choices.length)]);
        }, 12000);
    };
    motion.addEventListener("change", updateTimer);
    document.addEventListener("visibilitychange", updateTimer);
    window.addEventListener("pagehide", () => {
        pageActive = false;
        updateTimer();
    });
    window.addEventListener("pageshow", () => {
        pageActive = true;
        updateTimer();
    });
    updateTimer();
})();

document.querySelectorAll("[data-counter-for]").forEach((counter) => {
    const field = document.getElementById(counter.dataset.counterFor);
    if (!field) return;
    const update = () => {
        counter.textContent = `${Array.from(field.value).length} / ${field.maxLength}`;
    };
    update();
    counter.hidden = false;
    field.addEventListener("input", update);
});

document.querySelectorAll("form[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelectorAll("[data-image-input]").forEach((input) => {
    const field = input.closest(".field");
    const preview = field?.querySelector("[data-image-preview]");
    const error = field?.querySelector("[data-image-error]");
    const image = preview?.querySelector("img");
    if (!preview || !error || !image) return;
    let reader = null;
    const clearPreview = () => {
        preview.hidden = true;
        image.removeAttribute("src");
        if (reader && reader.readyState === FileReader.LOADING) reader.abort();
        reader = null;
    };
    input.addEventListener("change", () => {
        clearPreview();
        error.hidden = true;
        error.textContent = "";
        input.setCustomValidity("");
        const file = input.files?.[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            error.textContent = "图片不能超过 5 MB，请重新选择。";
            error.hidden = false;
            input.setCustomValidity(error.textContent);
            return;
        }
        reader = new FileReader();
        reader.addEventListener("load", () => {
            if (typeof reader?.result !== "string") return;
            image.src = reader.result;
            preview.hidden = false;
        });
        reader.readAsDataURL(file);
    });
    image.addEventListener("error", clearPreview);
    window.addEventListener("pagehide", clearPreview);
});

(() => {
    const form = document.getElementById("bulk-delete");
    if (!form) return;
    const selectAll = form.querySelector("[data-select-all]");
    const count = form.querySelector("[data-selected-count]");
    const button = form.querySelector("[data-bulk-delete]");
    const items = Array.from(document.querySelectorAll("[data-message-select]"))
        .filter((item) => item.form === form);
    if (!selectAll || !count || !button) return;
    const update = () => {
        const selected = items.filter((item) => item.checked).length;
        selectAll.checked = items.length > 0 && selected === items.length;
        selectAll.indeterminate = selected > 0 && selected < items.length;
        selectAll.disabled = items.length === 0;
        count.textContent = String(selected);
        button.disabled = selected === 0;
    };
    selectAll.addEventListener("change", () => {
        items.forEach((item) => { item.checked = selectAll.checked; });
        update();
    });
    items.forEach((item) => { item.addEventListener("change", update); });
    window.addEventListener("pageshow", update);
    update();
    selectAll.closest("label").hidden = false;
    count.closest(".selection-count").hidden = false;
})();
