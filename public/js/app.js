const config = {
    // Amount of posts to fetch per request
    fetch_amount: 5,
    // Available code themes
    themes: [
        "default",
        "dark",
        "monokai",
        "monokai-sublime",
        "atom-one-dark",
        "atom-one-light",
        "brown-paper",
        "codepen-embed",
        "github",
        "night-owl",
        "rainbow",
        "vs"
    ]
};

// Time formatting
function pluralizeString( str, quantity ) {
	return str + ( ( quantity != 1 ) && "s" || "" )
}

function niceTime( seconds ) {
    let t = 0

	if ( !seconds )
        return "a few seconds";

	if ( seconds < 60 )
    {
		t = Math.floor( seconds )
		return t + pluralizeString( " second", t )
    }

	if ( seconds < 60 * 60 )
    {
		t = Math.floor( seconds / 60 )
		return t + pluralizeString( " minute", t )
	}

	if ( seconds < 60 * 60 * 24 )
    {
		t = Math.floor( seconds / (60 * 60) )
		return t + pluralizeString( " hour", t )
	}

    if ( seconds < 60 * 60 * 24 * 30 )
    {
		t = Math.floor( seconds / ( 60 * 60 * 24 ) )
		return t + pluralizeString( " day", t )
	}

	if ( seconds < 60 * 60 * 24 * 30 )
    {
		t = Math.floor( seconds / ( 60 * 60 * 24 * 7 ) )
		return t + pluralizeString( " week", t )
	}

    if ( seconds < 60 * 60 * 24 * 365 )
    {
		t = Math.floor( seconds / ( 60 * 60 * 24 * 30 ) )
		return t + pluralizeString( " month", t )
	}

	t = Math.floor( seconds / ( 60 * 60 * 24 * 365 ) )
	return t + pluralizeString( " year", t )
}

// Animates Toast progress bar
function animateToast(toast) {
    toast.on('show.bs.toast', function() {
        $('.progress-bar').attr('aria-valuenow', 100);
    });
    toast.on('shown.bs.toast', function() {
        $(".progress-bar").each(function(i) {
            var displayTime = toast.attr('data-bs-delay');
            $(this).animate({
                width: $(this).attr('aria-valuenow') + '%'
            });
            $(this).css({
                webkittransition: 'width '+displayTime+'ms ease-in-out',
                moztransition: 'width '+displayTime+'ms ease-in-out',
                otransition: 'width '+displayTime+'ms ease-in-out',
                transition: 'width '+displayTime+'ms ease-in-out'
            });
            $(this).prop('Counter', 0).animate({
                Counter: $(this).attr('aria-valuenow')
            }, {
                duration: 8000,
                step: function(now) {
                    $(this).closest(".toast")
                        .find(".progressbar-number")
                        .text(Math.ceil(now));
                }
            });
        });
    });
}

// Adds a Toast
function addToast(msgTitle, msg, type = "default") {
    const newToast = `
        <div class="toast toast-${type}" data-bs-delay="5000">
            <div class="d-flex">
                <div class="toast-body w-100">
                    <span class="toast-title">${msgTitle}</span>
                    <p class="toast-message">${msg ? msg.replace('\n', '<br>') : ''}</p>
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="progress">
                <div class="progress-bar" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
    `;

    $('.toast-container').append(newToast);

    const toastContainer =  $('.toast-container');
    const toast = toastContainer.find('.toast:last');
    new bootstrap.Toast(toast);
    animateToast(toast);
    toast.toast('show');
    toast.on('hidden.bs.toast', function() {
        toast.remove();
    })
}

// Adds spinner to button and disables it
$original = [];
function showBtnLoad(btn, state = true) {
    if (state) {
        $original[btn] = btn.html();
        btn.addClass('disabled');
        btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>')
    } else {
        btn.removeClass('disabled');
        if ($original[btn]) {
            btn.html($original[btn]);
        }
    }
}

// Ajax helper
function ajaxSend(url, type, data, successCallback = null, errorCallback = null) {
    $.ajax({
        url: url,
        type: type,
        data: data,
        success: function (response) {
            console.log(response);
            if (!response.status || response.status != 'success') return addToast("Error", response.error_message ?? "Error", "danger");
            if (successCallback) successCallback(response);
        },
        error: function (response, textStatus, errorMessage) {
            console.log(response, textStatus, errorMessage);
            addToast(textStatus, typeof(errorMessage) == "string" ? errorMessage : textStatus, "danger");
            if (errorCallback) errorCallback(response, textStatus, errorMessage);
        }
    });
}

// Handle form submit
const form = $('form')[0];
$('form #submit').on("click", function() {
    if(form.checkValidity() === false) return true;
    $(this).addClass('disabled');
    $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>')
})

// Login/Register email input validation
$('.login-container #email').on('input', function() {
    const email = $(this);
    if (!email.val().includes('@') || email.val().charAt(email.val().length - 1) == '@') {
        email.addClass('is-invalid');
        email.removeClass('is-valid')
        email.find('valid-feedback').hide(); 
        email.find('invalid-feedback').show(); 
    } else {
        email.addClass('is-valid');
        email.removeClass('is-invalid')
        email.addClass('is-valid');
        email.find('valid-feedback').show(); 
        email.find('invalid-feedback').hide(); 
    };
})

// Search input
$('.navbar-search #search').on('keypress', function(e) {
    if (e.which == 13) window.location.href = "/search/index/" + $(this).val();
})

// Fix code highlighting
document.querySelectorAll("code").forEach(function(element) {
    element.innerHTML = element.innerHTML.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
});

// Enable tooltips
$(function () {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
})

// HTML encoding
function htmlEncode(value) {
    return $('<textarea/>').text(value).html();
}

function htmlDecode(value) {
    return $("<textarea/>").html(value).text();
}

// Confirmation
const confirmModal = $('#confirmModal');
function confirmAction(confirmCallback, actionName, actionDesc) {
    confirmModal.modal('show');

    if (actionName) confirmModal.find('.modal-title').html(actionName);
    if (actionDesc) confirmModal.find('.modal-body').html(actionDesc);

    const confirmBtn = confirmModal.find('#confirm');
    confirmBtn.unbind('click');
    confirmBtn.click(function() {
        if (confirmCallback) confirmCallback();
        confirmModal.modal('hide');
    })
}

// Updates settings based attributes
function onStorageUpdate() {
    $('body').attr('theme', JSON.parse(localStorage.getItem("darktheme")) ? 'dark' : 'light');
    $('body').attr('reducedmotion', JSON.parse(localStorage.getItem("reducedmotion")));
}

onStorageUpdate();

// Storage event handler
window.onstorage = () => {
    console.log("yes");
    onStorageUpdate(); // Check for updates
};

// Returns active theme
function getTheme() {
    return $('body').attr('theme');
}
