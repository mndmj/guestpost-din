(() => {
  const dialog = document.getElementById("gpm-shop-cart-modal");

  if (!dialog || typeof dialog.showModal !== "function") {
    return;
  }

  dialog.showModal();

  dialog.addEventListener("click", (event) => {
    if (event.target !== dialog) {
      return;
    }

    const bounds = dialog.getBoundingClientRect();
    if (
      event.clientX < bounds.left ||
      event.clientX > bounds.right ||
      event.clientY < bounds.top ||
      event.clientY > bounds.bottom
    ) {
      dialog.close();
    }
  });

  const url = new URL(window.location.href);
  for (const parameter of [
    "gpm_cart_pending",
    "gpm_shop_cart",
    "add-to-cart",
  ]) {
    url.searchParams.delete(parameter);
  }
  window.history.replaceState(window.history.state, "", url);
})();
