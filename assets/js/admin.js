(function () {
	var prefix = document.getElementById('lrps-prefix');
	if (prefix) {
		var fields = document.querySelectorAll('[data-autofield="1"]');
		Array.prototype.forEach.call(fields, function (field) {
			field.addEventListener('input', function () {
				field.setAttribute('data-touched', '1');
			});
		});
		prefix.addEventListener('input', function () {
			var value = prefix.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
			if (!value) {
				return;
			}
			Array.prototype.forEach.call(fields, function (field) {
				if (field.getAttribute('data-touched') === '1') {
					return;
				}
				var suffix = field.getAttribute('data-suffix');
				if (suffix) {
					field.value = value + '_' + suffix;
				}
			});
		});
	}

	var confirms = document.querySelectorAll('[data-confirm]');
	Array.prototype.forEach.call(confirms, function (form) {
		form.addEventListener('submit', function (event) {
			if (!window.confirm(form.getAttribute('data-confirm'))) {
				event.preventDefault();
			}
		});
	});
}());
