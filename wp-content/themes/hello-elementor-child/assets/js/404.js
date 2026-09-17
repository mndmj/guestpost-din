(() => {
  "use strict";
  const root = document.querySelector("main.gpm-404");
  if (!root) return;
  const scene = root.querySelector(".gpm-404__scene");
  const art = root.querySelector(".gpm-404__art");
  const toggle = root.querySelector(".gpm-404__motion");
  const reduced = window.matchMedia("(prefers-reduced-motion: reduce)");
  const pointer = window.matchMedia("(hover: hover) and (pointer: fine)");
  let paused = false;
  let frame = 0;

  function resetScene() {
    cancelAnimationFrame(frame);
    frame = 0;
    scene.style.transform = "";
  }

  function updateMotion() {
    const stopped = paused || reduced.matches;
    root.dataset.motion = stopped ? "paused" : "running";
    toggle.disabled = reduced.matches;
    toggle.setAttribute("aria-pressed", String(stopped));
    toggle.querySelector("span").textContent = reduced.matches
      ? toggle.dataset.reduced
      : stopped ? toggle.dataset.resume : toggle.dataset.pause;
    resetScene();
  }

  toggle.addEventListener("click", () => {
    paused = !paused;
    updateMotion();
  });
  reduced.addEventListener("change", updateMotion);
  pointer.addEventListener("change", resetScene);
  art.addEventListener("pointerleave", resetScene);
  // ponytail: native CSS does the animation; one frame only for pointer parallax.
  art.addEventListener("pointermove", (event) => {
    if (root.dataset.motion !== "running" || !pointer.matches || frame) return;
    const bounds = art.getBoundingClientRect();
    const x = (event.clientX - bounds.left) / bounds.width - 0.5;
    const y = (event.clientY - bounds.top) / bounds.height - 0.5;
    frame = requestAnimationFrame(() => {
      scene.style.transform = `translate(${x * 20}px, ${y * 16}px)`;
      frame = 0;
    });
  });
  toggle.hidden = false;
  updateMotion();
})();
