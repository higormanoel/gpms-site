(function () {
  "use strict";

  const menuButton = document.querySelector(".menu-toggle");
  const navigation = document.querySelector(".main-nav");
  const navLinks = Array.from(document.querySelectorAll(".main-nav a"));
  const video = document.querySelector(".hero-video");
  const playButton = document.querySelector(".video-play");
  const form = document.querySelector(".contact-form");
  const formStatus = document.querySelector(".form-status");

  function closeMenu() {
    if (!menuButton || !navigation) return;
    menuButton.setAttribute("aria-expanded", "false");
    navigation.classList.remove("is-open");
    document.body.classList.remove("menu-open");
  }

  if (menuButton && navigation) {
    menuButton.addEventListener("click", function () {
      const isOpen = menuButton.getAttribute("aria-expanded") === "true";
      menuButton.setAttribute("aria-expanded", String(!isOpen));
      navigation.classList.toggle("is-open", !isOpen);
      document.body.classList.toggle("menu-open", !isOpen);
    });

    navLinks.forEach(function (link) {
      link.addEventListener("click", closeMenu);
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") closeMenu();
    });
  }

  if (video && playButton) {
    playButton.addEventListener("click", function () {
      video.controls = true;
      video.play();
    });

    video.addEventListener("play", function () {
      playButton.classList.add("is-hidden");
    });

    video.addEventListener("pause", function () {
      if (video.currentTime < 0.2 || video.ended) playButton.classList.remove("is-hidden");
    });

    video.addEventListener("ended", function () {
      video.controls = false;
      video.load();
      playButton.classList.remove("is-hidden");
    });
  }

  const observedSections = navLinks
    .map(function (link) {
      const id = link.getAttribute("href");
      return id && id.startsWith("#") ? document.querySelector(id) : null;
    })
    .filter(Boolean);

  if ("IntersectionObserver" in window && observedSections.length) {
    const sectionObserver = new IntersectionObserver(
      function (entries) {
        const visible = entries
          .filter(function (entry) {
            return entry.isIntersecting;
          })
          .sort(function (a, b) {
            return b.intersectionRatio - a.intersectionRatio;
          })[0];

        if (!visible) return;
        navLinks.forEach(function (link) {
          link.classList.toggle("active", link.getAttribute("href") === "#" + visible.target.id);
        });
      },
      { rootMargin: "-20% 0px -60% 0px", threshold: [0.05, 0.2, 0.5] }
    );

    observedSections.forEach(function (section) {
      sectionObserver.observe(section);
    });
  }

  if (form && formStatus) {
    form.addEventListener("submit", async function (event) {
      event.preventDefault();

      if (!form.checkValidity()) {
        form.reportValidity();
        formStatus.textContent = "Preencha os campos obrigatórios para continuar.";
        return;
      }

      const submit = form.querySelector('button[type="submit"]');
      if (submit) submit.disabled = true;
      formStatus.textContent = "Enviando sua mensagem com segurança...";

      try {
        const response = await fetch(form.action, {
          method: "POST",
          body: new FormData(form),
          headers: { Accept: "application/json" },
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
          throw new Error(payload.message || "Não foi possível enviar a mensagem.");
        }
        form.reset();
        formStatus.textContent = payload.message;
      } catch (error) {
        formStatus.textContent =
          error instanceof Error
            ? error.message
            : "Não foi possível enviar agora. Fale conosco por email ou WhatsApp.";
      } finally {
        if (submit) submit.disabled = false;
      }
    });
  }
})();
