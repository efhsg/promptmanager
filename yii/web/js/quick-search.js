document.addEventListener("DOMContentLoaded", function() {
  const input = document.getElementById("quick-search-input");
  const dropdown = document.getElementById("quick-search-results");

  if (!input || !dropdown) return;

  let debounceTimer = null;
  let abortController = null;
  let activeIndex = -1;

  const groupLabels = {
    contexts: "Contexts",
    fields: "Fields",
    templates: "Templates",
    instances: "Generated",
    scratchPads: "Scratch Pads"
  };

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function highlightMatch(text, query) {
    if (!query) return escapeHtml(text);
    const escaped = escapeHtml(text);
    const regex = new RegExp("(" + query.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + ")", "gi");
    return escaped.replace(regex, "<mark>$1</mark>");
  }

  function renderResults(data, query) {
    let html = "";
    let hasResults = false;

    Object.entries(groupLabels).forEach(function([key, label]) {
      const items = data[key];
      if (!items || items.length === 0) return;

      hasResults = true;
      html += '<div class="quick-search-group">';
      html += '<div class="quick-search-group-title">' + escapeHtml(label) + "</div>";

      items.forEach(function(item) {
        html += '<a href="' + escapeHtml(item.url) + '" class="quick-search-item" data-url="' + escapeHtml(item.url) + '">';
        html += '<div class="quick-search-item-name">' + highlightMatch(item.name, query) + "</div>";
        html += '<div class="quick-search-item-subtitle">' + escapeHtml(item.subtitle) + "</div>";
        html += "</a>";
      });

      html += "</div>";
    });

    if (!hasResults) {
      html = '<div class="quick-search-empty">No results found</div>';
    }

    dropdown.innerHTML = html;
    activeIndex = -1;
    updateActiveItem();
  }

  function showDropdown() {
    dropdown.classList.add("show");
  }

  function hideDropdown() {
    dropdown.classList.remove("show");
    activeIndex = -1;
  }

  function getItems() {
    return dropdown.querySelectorAll(".quick-search-item");
  }

  function updateActiveItem() {
    const items = getItems();
    items.forEach(function(item, index) {
      item.classList.toggle("active", index === activeIndex);
    });

    if (activeIndex >= 0 && items[activeIndex]) {
      items[activeIndex].scrollIntoView({ block: "nearest" });
    }
  }

  function navigateToActive() {
    const items = getItems();
    if (activeIndex >= 0 && items[activeIndex]) {
      window.location.href = items[activeIndex].dataset.url;
    }
  }

  function performSearch(query) {
    if (abortController) {
      abortController.abort();
    }

    if (query.length < 2) {
      hideDropdown();
      return;
    }

    abortController = new AbortController();
    dropdown.innerHTML = '<div class="quick-search-loading">Searching...</div>';
    showDropdown();

    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    const headers = {
      "X-Requested-With": "XMLHttpRequest",
      "Accept": "application/json"
    };
    if (csrfToken) {
      headers["X-CSRF-Token"] = csrfToken.getAttribute("content");
    }

    fetch("/search/quick?q=" + encodeURIComponent(query), {
      method: "GET",
      headers: headers,
      signal: abortController.signal
    })
      .then(function(response) {
        return response.json();
      })
      .then(function(result) {
        if (result.success) {
          renderResults(result.data, query);
        } else {
          dropdown.innerHTML = '<div class="quick-search-empty">Search failed</div>';
        }
      })
      .catch(function(error) {
        if (error.name !== "AbortError") {
          dropdown.innerHTML = '<div class="quick-search-empty">Search failed</div>';
        }
      });
  }

  input.addEventListener("input", function() {
    const query = input.value.trim();

    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(function() {
      performSearch(query);
    }, 300);
  });

  input.addEventListener("keydown", function(e) {
    const items = getItems();

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        if (items.length > 0) {
          activeIndex = Math.min(activeIndex + 1, items.length - 1);
          updateActiveItem();
        }
        break;

      case "ArrowUp":
        e.preventDefault();
        if (items.length > 0) {
          activeIndex = Math.max(activeIndex - 1, 0);
          updateActiveItem();
        }
        break;

      case "Enter":
        e.preventDefault();
        navigateToActive();
        break;

      case "Escape":
        hideDropdown();
        input.blur();
        break;
    }
  });

  input.addEventListener("focus", function() {
    if (input.value.trim().length >= 2 && dropdown.innerHTML) {
      showDropdown();
    }
  });

  document.addEventListener("click", function(e) {
    if (!input.contains(e.target) && !dropdown.contains(e.target)) {
      hideDropdown();
    }
  });
});
