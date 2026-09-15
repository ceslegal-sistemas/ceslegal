/*
  [JS Index]
*/


/*
  1. preloader
  2. fadeIn.element
  3. page scroll
  4. navigation
  5. on scroll animation
  6. home fadeOut animation
  7. slick slider
  8. facts counter
  9. skills bar
  10. services accordion
  11. google maps zoom ON/OFF
  12. owl carousel
    12.1. owl team carousel
    12.2. owl inside carousel
	12.3. owl testimonials carousel
	12.4. owl news carousel
  13. swiper slider
  14. magnificPopup
  15. clone function
  16. contact form
    16.1. contact modal
  17. blog comment form
  18. toggle blog panels
*/


$(function() {
	"use strict";
	
	
	$(window).on("load", function() {
		// 1. preloader
		$("#preloader").fadeOut(600);
		$(".preloader-bg").delay(400).fadeOut(600);
		
		// 2. fadeIn.element
		setTimeout(function() {
			$(".fadeIn-element").delay(600).css({
				display: "none"
			}).fadeIn(800);
		}, 0);
		setTimeout(function() {
			$(".border-left").removeClass("left-position");
		}, 0);
		setTimeout(function() {
			$(".border-right").removeClass("right-position");
		}, 0);
		setTimeout(function() {
			$(".border-top").removeClass("top-position");
		}, 0);
		setTimeout(function() {
			$(".border-bottom").removeClass("bottom-position");
		}, 0);
		$(".hero-bg").addClass("hero-bg-show");
	});
	
	// 3. page scroll
	$('a[href*="#"]:not([href="#"])').on("click", function() {
		console.log("click");
		if (location.pathname.replace(/^\//, "") == this.pathname.replace(/^\//, "") && location.hostname == this.hostname) {
			var target = $(this.hash);
			target = target.length ? target : $('[name=" + this.hash.slice(1) + "]');
			if (target.length) {
				if ($(window).width() < 768) {
					$("html, body").animate({
						scrollTop: target.offset().top - 57
					}, 1000);
				} else {
					$("html, body").animate({
						scrollTop: target.offset().top - 58
					}, 1000);
				}
				return false;
			}
		}
	});
	
	// 4. navigation
	$("body").scrollspy({
		target: ".navbar",
		offset: 59
	});
	$(".navbar-collapse ul li a").on("click", function() {
		$(".navbar-toggle:visible").click();
	});
	
	$(window).on("scroll", function() {
		if ($(".navbar").offset().top > 50) {
			$(".navbar-bg-switch").addClass("main-navigation-bg");
		} else {
			$(".navbar-bg-switch").removeClass("main-navigation-bg");
		}
		
		// 5. on scroll animation
		if ($(this).scrollTop() > 100) {
			$(".to-top-arrow").addClass("show");
			$(".border-bottom, .border-left, .border-right").addClass("secondary-position");
			$(".blog-side-launcher").addClass("blog-side-launcher-color");
		} else {
			$(".to-top-arrow").removeClass("show");
			$(".border-bottom, .border-left, .border-right").removeClass("secondary-position");
			$(".blog-side-launcher").removeClass("blog-side-launcher-color");
		}
		
		// 6. home fadeOut animation
		$(".hero-heading.hero-heading-home, .hero-subheading.hero-subheading-home, .swiper-slide-pagination, .swiper-slide-controls-play-pause-wrapper, .hero-slider-bg-controls, .more-wraper-center.more-wraper-center-home").css("opacity", 1 - $(window).scrollTop() / 500);
	});
	
	// 7. slick slider
	$(".slick-left, .slick-right-alternative").slick({
		arrows: true,
		initialSlide: 0,
		infinite: true,
		slidesToShow: 1,
		slidesToScroll: 1,
		prevArrow: "<i class='slick-prev icon ion-chevron-left'></i>",
		nextArrow: "<i class='slick-next icon ion-chevron-right'></i>",
		fade: false,
		autoplay: true,
		autoplaySpeed: 4000,
		cssEase: "ease",
		speed: 800
	});
	
	// 8. facts counter
	$(".facts-counter-number").appear(function() {
		var count = $(this);
		count.countTo({
			from: 0,
			to: count.html(),
			speed: 1200,
			refreshInterval: 60
		});
	});
	
	// 9. skills bar
	$(".show-skillbar").appear(function() {
		$(".skillbar").skillBars({
			from: 0,
			speed: 4000,
			interval: 100,
			decimals: 0
		});
	});
	
	// 10. services accordion
	$(".services-accordion ul li span").on("click", function() {
		$(this).parent("li").siblings("li.toggled").removeClass("toggled").children("ul").stop(true, true).slideUp();
		if (!$(this).parent().hasClass("toggled")) {
			$(this).next("ul").stop(true, true).slideDown();
			$(this).parent().addClass("toggled");
		} else {
			$(this).next("ul").stop(true, true).slideUp();
			$(this).parent().removeClass("toggled");
		}
	});
	
	// 11. google maps zoom ON/OFF
	$(".google-maps").on("click", function() {
		$('.google-maps iframe').css("pointer-events", "auto");
	});
	$(".google-maps").on("mouseleave", function() {
		$('.google-maps iframe').css("pointer-events", "none");
	});
	
	// 12. owl carousel
	// 12.1. owl team carousel
	$(".owl-carousel-team").owlCarousel({
		loop: false,
		center: false,
		margin: 25,
		autoplay: false,
		autoplaySpeed: 1000,
		autoplayTimeout: 5000,
		smartSpeed: 450,
		nav: false,
		nav: true,
		navText: ["<i class='ion-chevron-left'></i>", "<i class='ion-chevron-right'></i>"],
		navContainer: '.owl-nav-custom-team',
		responsive: {
            0: {
                items: 1
            },
            768: {
                items: 2
            },
            1170: {
                items: 3
            }
        }
	});
	// 12.2. owl inside carousel
	$(".owl-carousel-inside").owlCarousel({
		loop: false,
		center: false,
		margin: 25,
		autoplay: false,
		autoplaySpeed: 1000,
		autoplayTimeout: 5000,
		smartSpeed: 450,
		nav: false,
		nav: true,
		navText: ["<i class='ion-chevron-left'></i>", "<i class='ion-chevron-right'></i>"],
		navContainer: '.owl-nav-custom-inside',
		responsive: {
            0: {
                items: 1
            },
            768: {
                items: 2
            },
            1170: {
                items: 3
            }
        }
	});
	// 12.3. owl testimonials carousel
	$("#owl-carousel-testimonials").owlCarousel({
        loop: true,
        center: true,
        items: 1,
        margin: 0,
        autoplay: true,
        autoplaySpeed: 1000,
        autoplayTimeout: 4000,
        smartSpeed: 450,
        nav: false,
        animateOut: "fadeOut",
        animateIn: "fadeIn"
    });
    // 12.4. owl news carousel
	$("#owl-carousel-news-1").owlCarousel({
		loop: false,
		center: false,
		autoplay: false,
		autoplaySpeed: 1000,
		autoplayTimeout: 5000,
		smartSpeed: 450,
		nav: false,
		nav: true,
		navText: ["<i class='ion-chevron-left'></i>", "<i class='ion-chevron-right'></i>"],
		navContainer: '.owl-nav-custom-news-all.owl-nav-custom-news-1',
		responsive: {
			0: {
				items: 1,
				margin: 15
			},
			768: {
				items: 1,
				margin: 50
			},
			980: {
				items: 1,
				margin: 50
			},
			1240: {
				items: 2,
				margin: 50
			}
		}
	});
	$("#owl-carousel-news-2").owlCarousel({
		loop: false,
		center: false,
		autoplay: false,
		autoplaySpeed: 1000,
		autoplayTimeout: 5000,
		smartSpeed: 450,
		nav: false,
		nav: true,
		navText: ["<i class='ion-chevron-left'></i>", "<i class='ion-chevron-right'></i>"],
		navContainer: '.owl-nav-custom-news-all.owl-nav-custom-news-2',
		responsive: {
			0: {
				items: 1,
				margin: 15
			},
			768: {
				items: 1,
				margin: 50
			},
			980: {
				items: 1,
				margin: 50
			},
			1240: {
				items: 2,
				margin: 50
			}
		}
	});
	$("#owl-carousel-news-3").owlCarousel({
		loop: false,
		center: false,
		autoplay: false,
		autoplaySpeed: 1000,
		autoplayTimeout: 5000,
		smartSpeed: 450,
		nav: false,
		nav: true,
		navText: ["<i class='ion-chevron-left'></i>", "<i class='ion-chevron-right'></i>"],
		navContainer: '.owl-nav-custom-news-all.owl-nav-custom-news-3',
		responsive: {
			0: {
				items: 1,
				margin: 15
			},
			768: {
				items: 1,
				margin: 50
			},
			980: {
				items: 1,
				margin: 50
			},
			1240: {
				items: 2,
				margin: 50
			}
		}
	});
	$("#owl-carousel-news-4").owlCarousel({
		loop: false,
		center: false,
		autoplay: false,
		autoplaySpeed: 1000,
		autoplayTimeout: 5000,
		smartSpeed: 450,
		nav: false,
		nav: true,
		navText: ["<i class='ion-chevron-left'></i>", "<i class='ion-chevron-right'></i>"],
		navContainer: '.owl-nav-custom-news-all.owl-nav-custom-news-4',
		responsive: {
			0: {
				items: 1,
				margin: 15
			},
			768: {
				items: 1,
				margin: 50
			},
			980: {
				items: 1,
				margin: 50
			},
			1240: {
				items: 2,
				margin: 50
			}
		}
	});
	$("#owl-carousel-news-all").owlCarousel({
		loop: false,
		center: false,
		autoplay: false,
		autoplaySpeed: 1000,
		autoplayTimeout: 5000,
		smartSpeed: 450,
		nav: false,
		nav: true,
		navText: ["<i class='ion-chevron-left'></i>", "<i class='ion-chevron-right'></i>"],
		navContainer: '.owl-nav-custom-news-all.owl-nav-custom-news-all',
		responsive: {
			0: {
				items: 1,
				margin: 15
			},
			768: {
				items: 1,
				margin: 50
			},
			980: {
				items: 1,
				margin: 50
			},
			1240: {
				items: 2,
				margin: 50
			}
		}
	});
	// owl services 2 all carousel
	$("#owl-carousel-services-all").owlCarousel({
		loop: false,
		center: false,
		autoplay: false,
		autoplaySpeed: 1000,
		autoplayTimeout: 5000,
		smartSpeed: 450,
		nav: false,
		nav: true,
		navText: ["<i class='ion-chevron-left'></i>", "<i class='ion-chevron-right'></i>"],
		navContainer: '.owl-nav-custom-news-all.owl-nav-custom-services-all',
		responsive: {
			0: {
				items: 1,
				margin: 15
			},
			768: {
				items: 1,
				margin: 50
			},
			980: {
				items: 1,
				margin: 50
			},
			1240: {
				items: 2,
				margin: 50
			}
		}
	});
	
	// 13. swiper slider
    var swiper = new Swiper(".swiper-container-wrapper .swiper-container", {
        preloadImages: false,
        autoplay: {
            delay: 4000,
            disableOnInteraction: false
        },
        init: true,
        loop: false,
        speed: 1200,
        grabCursor: true,
        mousewheel: false,
        keyboard: true,
        simulateTouch: true,
        parallax: true,
        effect: "slide",
        pagination: {
            el: ".swiper-slide-pagination",
            clickable: true
        },
        navigation: {
            nextEl: ".slide-next",
            prevEl: ".slide-prev"
        }
    });
    swiper.on("slideChangeTransitionStart", function() {
        $(".slider-progress-bar").removeClass("slider-active");
        $(".hero-bg").find("video").each(function() {
            this.pause();
        });
    });
    swiper.on("slideChangeTransitionEnd", function() {
        $(".slider-progress-bar").addClass("slider-active");
        $(".hero-bg").find("video").each(function() {
            this.play();
        });
    });
    swiper.on("slideChangeTransitionStart", function() {
        $(".slider-progress-bar").removeClass("slider-active");
    });
    swiper.on("slideChangeTransitionEnd", function() {
        $(".slider-progress-bar").addClass("slider-active");
    });
    var playButton = $(".swiper-slide-controls-play-pause-wrapper");
    function autoEnd() {
        playButton.removeClass("slider-on-off");
        swiper.autoplay.stop();
    }
    function autoStart() {
        playButton.addClass("slider-on-off");
        swiper.autoplay.start();
    }
    playButton.on("click", function() {
        if (playButton.hasClass("slider-on-off")) autoEnd();
        else autoStart();
        return false;
    });
	
	// 14. magnificPopup
    $(".popup-photo").magnificPopup({
        type: "image",
        gallery: {
            enabled: false,
            tPrev: "",
            tNext: "",
            tCounter: "%curr% / %total%"
        },
        removalDelay: 100,
        mainClass: "mfp-fade",
        fixedContentPos: false
    });
	$(".popup-photo-gallery").each(function() {
        $(this).magnificPopup({
            delegate: "a",
            type: "image",
            gallery: {
                enabled: true
            },
            removalDelay: 100,
            mainClass: "mfp-fade",
            fixedContentPos: false
        });
    });
	
	// 15. clone function
    $.fn.duplicate = function(count, cloneEvents, callback) {
        var stack = [],
            el;
        while (count--) {
            el = this.clone(cloneEvents);
            callback && callback.call(el);
            stack.push(el.get()[0]);
        }
        return this.pushStack(stack);
    };
    $("<div class='vertical-lines-wrapper'></div>").appendTo(".vertical-lines");
    $("<div class='vertical-effect'></div>").duplicate(3).appendTo(".vertical-lines-wrapper");
	
	// 16. contact form
    $("form#form").on("submit", function() {
        $("form#form .error").remove();
        var s = !1;
        if ($(".requiredField").each(function() {
                if ("" === jQuery.trim($(this).val())) $(this).prev("label").text(), $(this).parent().append('<span class="error">This field is required</span>'), $(this).addClass(
                    "inputError"), s = !0;
                else if ($(this).hasClass("email")) {
                    var r = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
                    r.test(jQuery.trim($(this).val())) || ($(this).prev("label").text(), $(this).parent().append('<span class="error">Invalid email address</span>'), $(this).addClass(
                        "inputError"), s = !0);
                }
            }), !s) {
            $("form#form input.submit").fadeOut("normal", function() {
                $(this).parent().append("");
            });
            var r = $(this).serialize();
            $.post($(this).attr("action"), r, function() {
                $("form#form").slideUp("fast", function() {
                    $(this).before('<div class="success">Your email was sent successfully.</div>');
                });
            });
        }
        return !1;
    });
	// 16.1. contact modal
    $(".contact-modal-launcher, .contact-modal-closer").on("click", function(e) {
        e.preventDefault();
        if ($(".contact-modal").hasClass("open")) {
            $(".contact-modal").removeClass("open");
        } else {
            $(".contact-modal").addClass("open");
        }
    });
	
	// 17. blog comment form
    $("form#form-3").on("submit", function() {
        $("form#form-3 .error-c").remove();
        var s = !1;
        if ($(".requiredField-c").each(function() {
                if ("" === jQuery.trim($(this).val())) $(this).prev("label").text(), $(this).parent().append('<span class="error-c">This field is required</span>'), $(this).addClass(
                    "inputError"), s = !0;
                else if ($(this).hasClass("email")) {
                    var r = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
                    r.test(jQuery.trim($(this).val())) || ($(this).prev("label").text(), $(this).parent().append('<span class="error-c">Invalid email address</span>'), $(this).addClass(
                        "inputError"), s = !0);
                }
            }), !s) {
            $("form#form-3 input.submit").fadeOut("normal", function() {
                $(this).parent().append("");
            });
            var r = $(this).serialize();
            $.post($(this).attr("action"), r, function() {
                $("form#form-3").slideUp("fast", function() {
                    $(this).before('<div class="success">Your email was sent successfully.</div>');
                });
            });
        }
        return !1;
    });
	
	// 18. toggle blog panels
    $(".blog-side-launcher").on("click", function() {
        var divClass = $(this).attr("data-id");
        if ($(this).hasClass("open")) {
            $(this).removeClass("open");
            $("." + divClass).addClass("open");
        } else {
            $(this).addClass("open");
            $("." + divClass).addClass("open");
        }
    });
    $(".blog-side-launch, .blog-side-text").on("click", function() {
        $(".panel-from-left-blog, .panel-overlay-from-right-blog").removeClass("open");
    });
	
	
});