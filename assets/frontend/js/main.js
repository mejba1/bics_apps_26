(function ($) {
    "use strict";
    jQuery(window).on("load", function () {
        $(".preloader").delay(1600).fadeOut("slow");
    });
	
    $(".hamburger").on("click", function (event) {
        $(this).toggleClass("h-active");
        $(".main-nav").toggleClass("slidenav");
    });
	
    $(".header-home .main-nav ul li  a").on("click", function (event) {
        $(".hamburger").removeClass("h-active");
        $(".main-nav").removeClass("slidenav");
    });
	
    $(".main-nav .fl").on("click", function (event) {
        var $fl = $(this);
        $(this).parent().siblings().find(".sub-menu").slideUp();
        $(this).parent().siblings().find(".fl").addClass("flaticon-plus").text("+");
        if ($fl.hasClass("flaticon-plus")) {
            $fl.removeClass("flaticon-plus").addClass("flaticon-minus").text("-");
        } else {
            $fl.removeClass("flaticon-minus").addClass("flaticon-plus").text("+");
        }
        $fl.next(".sub-menu").slideToggle();
    });
	
    $(window).on("scroll", function () {
        var scroll = $(window).scrollTop();
        if (scroll >= 20) {
            $(".header-area").addClass("sticky");
        } else {
            $(".header-area").removeClass("sticky");
        }
    });
	
    $("#datepicker").datepicker();
    var x, i, j, l, ll, selElmnt, a, b, c;
    x = document.getElementsByClassName("custom-select");
    l = x.length;
    for (i = 0; i < l; i++) {
        selElmnt = x[i].getElementsByTagName("select")[0];
        ll = selElmnt.length;
        a = document.createElement("DIV");
        a.setAttribute("class", "select-selected");
        a.innerHTML = selElmnt.options[selElmnt.selectedIndex].innerHTML;
        x[i].appendChild(a);
        b = document.createElement("DIV");
        b.setAttribute("class", "select-items select-hide");
        for (j = 1; j < ll; j++) {
            c = document.createElement("DIV");
            c.innerHTML = selElmnt.options[j].innerHTML;
            c.addEventListener("click", function (e) {
                var y, i, k, s, h, sl, yl;
                s = this.parentNode.parentNode.getElementsByTagName("select")[0];
                sl = s.length;
                h = this.parentNode.previousSibling;
                for (i = 0; i < sl; i++) {
                    if (s.options[i].innerHTML === this.innerHTML) {
                        s.selectedIndex = i;
                        h.innerHTML = this.innerHTML;
                        y = this.parentNode.getElementsByClassName("same-as-selected");
                        yl = y.length;
                        for (k = 0; k < yl; k++) {
                            y[k].removeAttribute("class");
                        }
                        this.setAttribute("class", "same-as-selected");
                        break;
                    }
                }
                h.click();
            });
            b.appendChild(c);
        }
        x[i].appendChild(b);
        a.addEventListener("click", function (e) {
            e.stopPropagation();
            closeAllSelect(this);
            this.nextSibling.classList.toggle("select-hide");
            this.classList.toggle("select-arrow-active");
        });
    }
    function closeAllSelect(elmnt) {
        var x,
            y,
            i,
            xl,
            yl,
            arrNo = [];
        x = document.getElementsByClassName("select-items");
        y = document.getElementsByClassName("select-selected");
        xl = x.length;
        yl = y.length;
        for (i = 0; i < yl; i++) {
            if (elmnt === y[i]) {
                arrNo.push(i);
            } else {
                y[i].classList.remove("select-arrow-active");
            }
        }
        for (i = 0; i < xl; i++) {
            if (arrNo.indexOf(i)) {
                x[i].classList.add("select-hide");
            }
        }
    }
    document.addEventListener("click", closeAllSelect);
    document.querySelectorAll(".sidebar-style-two i").forEach((element) => {
        element.addEventListener("click", () => {
            document.querySelectorAll(".schedule-sidebar").forEach((element) => element.classList.add("sb-active"));
        });
    });
    document.querySelectorAll(".sb-toggle-icon").forEach((element) => {
        element.addEventListener("click", () => {
            document.querySelectorAll(".schedule-sidebar").forEach((element) => element.classList.remove("sb-active"));
        });
    });
    function timeConverter(UNIX_timestamp) {
        var a = new Date(UNIX_timestamp * 1000);
        var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        var year = a.getFullYear();
        var month = months[a.getMonth()];
        var date = a.getDate();
        var hour = a.getHours();
        var min = a.getMinutes();
        var sec = a.getSeconds();
        var time = date + " " + month + " " + year + " " + hour + ":" + min + ":" + sec;
        console.log(date);
        $("#timer #days").html(date);
        $("#timer #hours").html(hour);
        $("#timer #minutes").html(min);
        $("#timer #seconds").html(sec);
    }
    function makeTimer() {
        // Event date is set in Admin > Pages > Site-wide > Event sidebar & countdown
        var endTime = new Date(window.BICS_EVENT_DATE || "September 17, 2024 00:00:00");
        var endTime = Date.parse(endTime) / 1000;
        var now = new Date();
        var now = Date.parse(now) / 1000;
        var timeLeft = Math.max(0, (endTime - now) || 0);
        var days = Math.floor(timeLeft / 86400);
        var hours = Math.floor((timeLeft - days * 86400) / 3600);
        var Xmas95 = new Date("December 25, 1995 23:15:30");
        var hour = Xmas95.getHours();
        var minutes = Math.floor((timeLeft - days * 86400 - hours * 3600) / 60);
        var seconds = Math.floor(timeLeft - days * 86400 - hours * 3600 - minutes * 60);
        if (hours < "10") {
            hours = "0" + hours;
        }
        if (minutes < "10") {
            minutes = "0" + minutes;
        }
        if (seconds < "10") {
            seconds = "0" + seconds;
        }
        $("#timer #days").html(days);
        $("#timer #hours").html(hours);
        $("#timer #minutes").html(minutes);
        $("#timer #seconds").html(seconds);
        $("#timer_h #days_h").html(days);
        $("#timer_h #hours_h").html(hours);
        $("#timer_h #minutes_h").html(minutes);
        $("#timer_h #seconds_h").html(seconds);
        $("#timer_h2 #days_h2").html(days);
        $("#timer_h2 #hours_h2").html(hours);
        $("#timer_h2 #minutes_h2").html(minutes);
        $("#timer_h2 #seconds_h2").html(seconds);
    }
    setInterval(function () {
        makeTimer();
    }, 1000);
	
})(jQuery);
