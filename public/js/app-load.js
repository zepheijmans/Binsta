let progress_bar_width = 0;
function add_progress(elem, len) {
    if (!$(elem).length) return check_element(elem);

    progress_bar_width += 100 / len;
    $("#progress-bar").animate({ width: progress_bar_width + "%" }, 0, function () {
        if (document.getElementById("progress-bar").style.width == "100%") {
            $("#page-load").hide();
            $("#page-content").fadeIn("fast");
            $("#progress-bar").fadeOut("slow");
        }
    });
}

document.addEventListener('readystatechange', (event) => {
    if (document.readyState != "interactive") return;

    const all = document.getElementsByTagName("*");
    for (let i = 0, max = all.length; i < max; i++) {
        add_progress(all[i], all.length);
    }
});
