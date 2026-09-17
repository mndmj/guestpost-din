(() => {
	const table = document.querySelector(".wp-list-table th.column-din_package_name")?.closest("table");
	if (!table || table.closest(".din-order-table-scroll")) return;
	const region = document.createElement("div");
	region.className = "din-order-table-scroll";
	region.setAttribute("role", "region");
	region.setAttribute("aria-label", "Orders and package details");
	region.tabIndex = 0;
	table.before(region);
	region.append(table);
})();
