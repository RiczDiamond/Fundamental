	$().ready(function() {

		$document = $(document);

		doc_height = $document.height();
		window_height = $(window).height();
		title = $(document).find('title').text();
		content = $('#content').html();
		path = window.location.pathname;

		$('body').addClass('loaded');
		$('body').addClass('changed');
		setTimeout(function(){ $('body').removeClass('changed'); }, 800);

		$(window).on('resize', function(){
			doc_height = $document.height();
			window_height = $(window).height();
		});

		on_scroll();

		// if(path != '/') {
		// 	var anchor = $('#menu a[href="'+path+'"]');
		// 	title = anchor.html();
		// 	anchor.addClass('active');
		// 	document.title = title;
		// } else {
		// 	$('#menu a[href="/"]').addClass('active');
		// }

		window.onpopstate = function(e){
			if(e.state){
				$('#content').html(e.state.html);
				document.title = e.state.pageTitle;
				$('#menu a').removeClass('active');
				$('#menu a:contains("'+e.state.pageTitle+'")').addClass('active');
			}
		};

		// window.history.replaceState({'html':content,'pageTitle':title},title);

		$('a.logo svg').children('path').each(function(index) {

			var path = $(this);

			setTimeout(function(){ $(path).addClass('loaded'); }, (index * 75) + 100);

		});

		$document.on('click', 'span.copy', function(event){

			event.preventDefault();

			var content = $(this).html();
			var span = $(this);
			
			if(updateClipboard(content) == '1') {
				span.addClass('copied');
				setTimeout(function(){ span.removeClass('copied'); }, 500);
			} else {
				span.addClass('copy-failed');
			}

		});

		$document.on('change', 'form#offerte input[type="checkbox"]', function(event){

			event.preventDefault();

			$('div.offerte div#content ul').html('');

			$('form#offerte input[type="checkbox"]').each(function(event){

				console.log(this);

				var data = $(this).parents('label').html();

				if($(this).is(':checked')) {
					$('div.offerte div#content ul').append('<li>'+data+'</li>');
				}

			});

		});

		$document.on('keyup', 'form#offerte input[type="text"]', function(event){

			event.preventDefault();

			if($(this).attr('name') == 'company') {
				$('span#companyname').html($(this).val());
			}

			if($(this).attr('name') == 'name') {
				$('span#contact').html('t.a.v. '+$(this).val());
			}

			if($(this).attr('name') == 'email') {
				$('span#email').html($(this).val());
			}

		});

		$document.on('submit', 'form.contact-form', function(event){

			var form = $(this);
			var button = form.find('button[type="submit"]');

			if(form.hasClass('submitting')) {
				event.preventDefault();
				return false;
			}

			form.addClass('submitting');

			if(button.length) {
				button.data('original-label', button.text());
				button.text('Versturen...');
				button.prop('disabled', true);
			}

			setTimeout(function(){
				if(form.hasClass('submitting')) {
					form.removeClass('submitting');
					if(button.length) {
						button.prop('disabled', false);
						button.text(button.data('original-label') || 'Verstuur bericht');
					}
				}
			}, 8000);

		});

		$document.on('click', 'section.services ul li a', function(event){

			event.preventDefault();

			$('div.block').removeClass('active');

			var id = $(this).attr('href');
			var title = $(id);
			var offset = title.offset().top;
			
			window.scrollTo({top: (offset - 100), behavior: 'smooth'});

			setTimeout(function(){ title.parent('div').addClass('active'); }, 500);

		});

		/*

		$document.on('mouseenter', 'img.video-attached', function(event){

			var src = $(this).attr('video-src');

			$(this).before('<div class="video"><video autoplay nocontrols loop><source src="'+src+'" type="video/mp4"></video></div>');

		});

		$document.on('mouseleave', 'video', function(event){

			$(this).remove();

		});

		*/

		// $document.on('click', 'a.logo', function(event){

		// 	event.preventDefault();

		// 	$('#menu ul a').removeClass('active');

		// 	if(window.location.pathname == '/') {
		// 		$('body').addClass('reloading');
		// 		window.scrollTo(0, 0);
		// 		setTimeout(function(){ location.reload(); }, 250);
		// 	} else {

		// 		var title = 'Ontbrand - Brandschone Online Techniek';

		// 		$.ajax({
		// 			type: 'GET',
		// 			url: '/page/home.html',
		// 			success: function(msg) {
		// 				$('#content').html(msg);
		// 				$('#menu.expanded a.menu').click();

		// 				$('body').addClass('changed');
		// 				setTimeout(function(){ $('body').removeClass('changed'); }, 800);

		// 				document.title = title;
		// 				window.history.pushState({'html':msg,'pageTitle':title},'', '/');
		// 			}
		// 		});

		// 	}

		// });

		// $document.on('click', '#menu ul a', function(event){

		// 	event.preventDefault();

		// 	if($(this).hasClass('active')) {

		// 		$('body').addClass('reloading');
		// 		setTimeout(function(){ location.reload(); }, 250);

		// 	} else {

		// 		var page = $(this).attr('data-page');
		// 		var title = $(this).html();
		// 		var anchor = $(this).attr('href');
		// 		$('#menu ul a').removeClass('active');
		// 		$(this).addClass('active');

		// 		$.ajax({
		// 			type: 'GET',
		// 			url: '/page/'+page+'.html',
		// 			success: function(msg) {
		// 				$('#content').html(msg);
		// 				$('#menu.expanded a.menu').click();

		// 				$('body').addClass('changed');
		// 				setTimeout(function(){ $('body').removeClass('changed'); }, 800);

		// 				document.title = title;
		// 				window.history.pushState({'html':msg,'pageTitle':title},'', anchor);
		// 				window.scrollTo(0, 0);

		// 				setTimeout(function(){ doc_height = $document.height(); }, 500);

		// 			}
		// 		});

		// 	}

		// });

		$document.on('click', '#menu.collapsed a.menu', function(event){

			event.preventDefault();

			$menu = $('div#menu');

			$menu.removeClass('collapsed');
			$menu.addClass('expanded');
			$('body').addClass('menu-open');

			setTimeout(function(){ $('footer').addClass('show'); }, 300);

		});

		$document.on('click', '#menu.expanded a.menu', function(event){

			event.preventDefault();

			$menu = $('div#menu');

			$menu.addClass('collapsed');
			$menu.removeClass('expanded');
			$('body').removeClass('menu-open');
			$('footer').removeClass('show');

		});

		$document.on('click', 'ul.language a', function(event){

			event.preventDefault();

			if($(this).hasClass('active')) {
				return false;
			}

			$('div.page').removeClass('now-visible')
			$('div.page').addClass('not-visible')

			var language = $(this).attr('data-language');
			var ele = $(this);

			$.ajax({
				type: 'GET',
				url: '/inc/language.php',
				data: 'language='+language,
				dataType: 'json',
				success: function(msg) {

					try {
						$.each(msg, function(k, v) {
							$('#'+k).html(v);
						});

						$('ul.language a').removeClass('active');
						$(ele).addClass('active');
						$('div.page').removeClass('not-visible');
						$('div.page').addClass('now-visible');
					} catch(e) {
						console.log(e);
						console.log(msg);
					}

				}
			});

		});

		$('div.page').each(function() {

			if(inviewport($(this))) {
				$(this).addClass('now-visible')
				$(this).removeClass('not-visible')
			} else {
				$(this).removeClass('now-visible')
				$(this).addClass('not-visible')
			}
		});

		$(window).on('scroll', function(event) {
			on_scroll();
		});


	});

	function on_scroll(ele) {

		var scrolled = $(window).scrollTop();
		var where_are_we = scrolled / ((doc_height - window_height) / 100);

		$('div.bar.last').css('background','linear-gradient(to bottom, #ddd '+where_are_we+'%, rgba(0,0,0,.02) '+where_are_we+'%)');

		if(scrolled > 10) {
			$('body').addClass('scrolled');
		} else {
			$('body').removeClass('scrolled');
		}

		if(scrolled < -120) {
			$('body').addClass('reloading');
			setTimeout(function(){ location.reload(); }, 250);
		}

	}

	function inviewport(elem) {
		var elementTop = $(elem).offset().top;
		var elementBottom = elementTop + $(elem).outerHeight();

		var viewportTop = $(window).scrollTop();
		var viewportBottom = viewportTop + $(window).height();

		return elementBottom > viewportTop && elementTop < viewportBottom;
	}

	function updateClipboard(newClip) {

		navigator.clipboard.writeText(newClip).then(function() {
			return 1;
		}, function() {
			return 2;
		});

		return 1;
	}