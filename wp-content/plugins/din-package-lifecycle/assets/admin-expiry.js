(() => {
	document.querySelectorAll(".din-package-expiry").forEach((editor) => {
		const duration = editor.querySelector("select");
		const years = editor.querySelector('input[type="number"]');
		const maximumYears = Number(years.max);
		const [baseYear, baseMonth, baseDay] = editor.getAttribute("data-expiry-base").split("-").map(Number);
		const date = editor.querySelector('input[type="date"]');
		const minimum = date.min;
		const reason = editor.querySelector("textarea");
		const apply = editor.querySelector('input[type="checkbox"]');
		const update = (changedDuration = false) => {
			const custom = duration.value === "custom";
			const validYears = /^[1-9][0-9]{0,3}$/.test(years.value) && Number(years.value) <= maximumYears;
			const annual = duration.value === "1" || duration.value === "2" || custom;
			const lifetime = duration.value === "lifetime";
			editor.querySelector(".din-package-expiry-years").hidden = !custom;
			years.disabled = !custom;
			years.required = apply.checked && custom;
			years.min = apply.checked && custom ? "1" : "";
			years.max = apply.checked && custom ? String(maximumYears) : "";
			years.step = apply.checked && custom ? "1" : "any";
			years.setCustomValidity(apply.checked && custom && !validYears ? "Enter a valid whole number of years." : "");
			editor.querySelector(".din-package-expiry-date").hidden = !annual;
			editor.querySelector(".din-package-expiry-lifetime").hidden = !lifetime;
			date.disabled = !annual;
			if (!annual || (custom && !validYears)) date.value = "";
			else if (changedDuration) {
				if (custom) {
					const targetYear = baseYear + Number(years.value);
					const day = Math.min(baseDay, new Date(Date.UTC(targetYear, baseMonth, 0)).getUTCDate());
					date.value = `${targetYear}-${String(baseMonth).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
				} else date.value = editor.getAttribute(`data-expiry-${duration.value}`);
			}
			date.required = apply.checked && annual;
			date.min = apply.checked && annual ? minimum : "";
			reason.required = apply.checked && (annual || lifetime);
			duration.setCustomValidity(apply.checked && !annual && !lifetime ? "Select a duration to create a payment order." : "");
		};
		apply.disabled = false;
		duration.addEventListener("change", () => update(true));
		years.addEventListener("input", () => update(true));
		apply.addEventListener("change", () => update());
		update();
	});
})();
