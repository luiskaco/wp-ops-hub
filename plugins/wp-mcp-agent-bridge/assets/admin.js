document.addEventListener('DOMContentLoaded', function () {
	var copyBtn = document.getElementById('mcp_copy_json_btn');
	var snippet = document.getElementById('mcp_json_snippet');
	var appPassInput = document.getElementById('mcp_app_password_input');
	var endpointInput = document.querySelector('.mcp-endpoint-input[readonly]');

	function encodeBase64Utf8(text) {
		if (window.TextEncoder) {
			var bytes = new TextEncoder().encode(text);
			var bin = '';
			for (var i = 0; i < bytes.length; i++) {
				bin += String.fromCharCode(bytes[i]);
			}
			return window.btoa(bin);
		}
		return window.btoa(unescape(encodeURIComponent(text)));
	}

	if (endpointInput) {
		endpointInput.addEventListener('click', function () {
			endpointInput.select();
		});
	}

	if (appPassInput && snippet) {
		appPassInput.addEventListener('input', function () {
			var username = appPassInput.getAttribute('data-username') || '';
			var endpoint = appPassInput.getAttribute('data-endpoint') || '';
			var rawPass = appPassInput.value.trim();
			var authValue = rawPass
				? 'Basic ' + encodeBase64Utf8(username + ':' + rawPass)
				: 'Basic <BASE64_DE_USUARIO_Y_PASSWORD>';

			snippet.value = JSON.stringify(
				{
					mcpServers: {
						wordpress: {
							url: endpoint,
							headers: {
								Authorization: authValue
							}
						}
					}
				},
				null,
				4
			);
		});
	}

	if (copyBtn && snippet && navigator.clipboard) {
		var defaultLabel = copyBtn.textContent;
		var copiedLabel = copyBtn.getAttribute('data-copied-label') || '¡Copiado!';

		copyBtn.addEventListener('click', function () {
			navigator.clipboard.writeText(snippet.value).then(function () {
				copyBtn.textContent = copiedLabel;
				setTimeout(function () {
					copyBtn.textContent = defaultLabel;
				}, 2000);
			});
		});
	}
});
