(function () {
  "use strict";

  document.querySelectorAll("[data-toggle-password]").forEach(function (button) {
    button.addEventListener("click", function () {
      const input = document.getElementById(button.dataset.togglePassword);
      if (!input) return;
      const reveal = input.type === "password";
      input.type = reveal ? "text" : "password";
      button.textContent = reveal ? "Ocultar" : "Mostrar";
      button.setAttribute("aria-label", reveal ? "Ocultar senha" : "Mostrar senha");
    });
  });

  const bodyEditor = document.getElementById("body");
  document.querySelectorAll("[data-prefix]").forEach(function (button) {
    button.addEventListener("click", function () {
      if (!bodyEditor) return;
      const prefix = button.dataset.prefix || "";
      const start = bodyEditor.selectionStart;
      const end = bodyEditor.selectionEnd;
      const selected = bodyEditor.value.slice(start, end);
      bodyEditor.setRangeText(prefix + selected, start, end, "end");
      bodyEditor.focus();
    });
  });

  const title = document.getElementById("title");
  const slug = document.getElementById("slug");
  if (title && slug) {
    title.addEventListener("blur", function () {
      if (slug.value.trim()) return;
      slug.value = title.value
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "");
    });
  }

  document.querySelectorAll("[data-confirm-delete]").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      if (!window.confirm("Excluir este artigo e suas imagens? Esta ação não poderá ser desfeita.")) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll("form").forEach(function (form) {
    form.addEventListener("submit", function () {
      const submit = form.querySelector('button[type="submit"]');
      if (!submit || !form.checkValidity()) return;
      submit.disabled = true;
      submit.dataset.originalText = submit.textContent;
      submit.textContent = "Processando…";
    });
  });
})();
