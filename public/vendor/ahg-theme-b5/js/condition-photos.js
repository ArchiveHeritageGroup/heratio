(function () {
  "use strict";
  var currentAnnotator = null;
  var annotatorModal = null;

  document.addEventListener("DOMContentLoaded", function () {
    var modalEl = document.getElementById("annotatorModal");
    if (modalEl) annotatorModal = new bootstrap.Modal(modalEl);

    var uploadForm = document.getElementById("upload-form");
    if (uploadForm) {
      uploadForm.addEventListener("submit", function (e) {
        e.preventDefault();
        uploadPhoto();
      });
    }

    var dropzone = document.getElementById("dropzone");
    var fileInput = document.getElementById("photo-file");
    if (dropzone && fileInput) {
      dropzone.addEventListener("click", function () {
        fileInput.click();
      });
      dropzone.addEventListener("dragover", function (e) {
        e.preventDefault();
        dropzone.classList.add("is-dragover");
      });
      dropzone.addEventListener("dragleave", function () {
        dropzone.classList.remove("is-dragover");
      });
      dropzone.addEventListener("drop", function (e) {
        e.preventDefault();
        dropzone.classList.remove("is-dragover");
        if (e.dataTransfer.files.length) fileInput.files = e.dataTransfer.files;
      });
    }

    document.addEventListener("click", function (e) {
      var target = e.target.closest("[data-action]");
      if (!target) return;
      var action = target.dataset.action;
      var photoId = target.dataset.photoId;
      var imageSrc = target.dataset.imageSrc;
      if (action === "annotate") openAnnotator(photoId, imageSrc);
      if (action === "delete") deletePhoto(photoId);
      if (action === "save-annotations") saveAnnotations();
    });
  });

  function uploadPhoto() {
    var form = document.getElementById("upload-form");
    var fileInput = document.getElementById("photo-file");
    if (!form || !fileInput) return;

    var formData = new FormData(form);
    if (!fileInput.files.length) {
      alert("Please select a photo");
      return;
    }
    formData.append("photo", fileInput.files[0]);

    var checkId = (window.AHG_CONDITION && window.AHG_CONDITION.checkId) || null;
    if (!checkId) {
      alert("Missing check ID");
      return;
    }

    fetch("/condition/check/" + checkId + "/upload", { method: "POST", body: formData })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) location.reload();
        else alert("Upload failed: " + (data.error || "Unknown error"));
      })
      .catch(function (err) { alert("Upload failed: " + err.message); });
  }

  function openAnnotator(photoId, imageSrc) {
    if (!photoId || !imageSrc) return;
    if (currentAnnotator) {
      currentAnnotator.destroy();
      currentAnnotator = null;
    }

    var modalEl = document.getElementById("annotatorModal");
    if (!modalEl || !annotatorModal) return;

    var initOnce = function () {
      modalEl.removeEventListener("shown.bs.modal", initOnce);
      currentAnnotator = new ConditionAnnotator("annotator-container", {
        photoId: photoId,
        imageUrl: imageSrc,
        readonly: false,
        showToolbar: true,
        saveUrl: "/condition/annotation/save",
        getUrl: "/condition/annotation/get"
      });
    };
    modalEl.addEventListener("shown.bs.modal", initOnce);
    annotatorModal.show();
  }

  function saveAnnotations() {
    if (!currentAnnotator) return;
    currentAnnotator.save().then(function () {
      annotatorModal.hide();
      location.reload();
    });
  }

  function deletePhoto(photoId) {
    var msg = (window.AHG_CONDITION && window.AHG_CONDITION.confirmDelete) || "Delete this photo?";
    if (!confirm(msg)) return;

    // Find and hide the photo card immediately for better UX
    var deleteBtn = document.querySelector('[data-action="delete"][data-photo-id="' + photoId + '"]');
    var photoCard = null;
    if (deleteBtn) {
      photoCard = deleteBtn.closest('.col-md-3, .col-md-4, .col-sm-6, .col-lg-3, .photo-card');
    }
    if (photoCard) {
      photoCard.style.opacity = '0.5';
      photoCard.style.pointerEvents = 'none';
    }

    fetch("/condition/photo/" + photoId + "/delete", { method: "POST" })
      .then(function (r) {
        if (!r.ok) throw new Error("Server error: " + r.status);
        return r.json();
      })
      .then(function (data) {
        if (data.success) {
          // Remove the element from DOM
          if (photoCard) {
            photoCard.remove();
            // Update photo count if displayed
            var countEl = document.querySelector('.photo-count, [data-photo-count]');
            if (countEl) {
              var count = parseInt(countEl.textContent) || 0;
              if (count > 0) countEl.textContent = count - 1;
            }
          } else {
            location.reload();
          }
        } else {
          // Restore the card and show error
          if (photoCard) {
            photoCard.style.opacity = '1';
            photoCard.style.pointerEvents = '';
          }
          alert("Delete failed: " + (data.error || "Unknown error"));
        }
      })
      .catch(function (err) {
        // Restore the card and show error
        if (photoCard) {
          photoCard.style.opacity = '1';
          photoCard.style.pointerEvents = '';
        }
        alert("Delete failed: " + err.message);
      });
  }
})();
