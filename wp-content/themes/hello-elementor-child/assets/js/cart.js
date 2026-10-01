(() => {
  const dialog = document.getElementById("gpm-cart-remove-dialog");
  if (!dialog) return;

  const rowSelector = ".wc-block-cart .wc-block-cart-items__row[data-cart-item-key]";
  const buttonSelector = ".wc-block-cart-item__remove-link";
  const product = document.getElementById("gpm-cart-remove-product");
  let pendingKey = null;
  let approvedButton = null;

  function currentButton() {
    const row = [...document.querySelectorAll(rowSelector)].find(
      (item) => item.dataset.cartItemKey === pendingKey,
    );
    return row?.querySelector(buttonSelector);
  }

  function removeItem(button) {
    if (!button?.isConnected || button.disabled || button.closest(".is-disabled")) return;

    // ponytail: replay the native handler; WooCommerce owns requests, errors and announcements.
    approvedButton = button;
    try {
      button.click();
    } finally {
      approvedButton = null;
    }
  }

  document.addEventListener("click", (event) => {
    const button = event.target instanceof Element ? event.target.closest(buttonSelector) : null;
    const row = button?.closest(rowSelector);
    if (!row || button === approvedButton || button.disabled) return;

    // Capture before React's Cart Block handler; delegated clicks also cover rerenders.
    event.preventDefault();
    event.stopPropagation();
    if (dialog.open) return;

    pendingKey = row.dataset.cartItemKey;
    product.textContent = row.querySelector(".wc-block-components-product-name")?.textContent.trim() || "";
    dialog.returnValue = "cancel";
    if (typeof dialog.showModal === "function") {
      dialog.showModal();
    } else {
      if (window.confirm(`${dialog.querySelector("h2").textContent}\n${product.textContent}`)) {
        removeItem(currentButton());
      }
      pendingKey = null;
    }
  }, true);

  dialog.querySelector("[data-gpm-cart-remove-confirm]").addEventListener("click", () => {
    // Resolve again: WooCommerce may have replaced the row while the dialog was open.
    const button = currentButton();
    dialog.close("remove");
    removeItem(button);
  });

  dialog.addEventListener("close", () => {
    if (dialog.returnValue !== "remove") currentButton()?.focus();
    pendingKey = null;
  });
})();
