!function(e){function t(e,t){var n=typeof e[t];return"function"===n||!("object"!=n||!e[t])||"unknown"==n}function n(e,t){return typeof e[t]!=x}function r(e,t){return!("object"!=typeof e[t]||!e[t])}function o(e){window.console&&window.console.log&&window.console.log("RangyInputs not supported in your browser. Reason: "+e)}function a(e,t,n){return 0>t&&(t+=e.value.length),typeof n==x&&(n=t),0>n&&(n+=e.value.length),{start:t,end:n}}function c(e,t,n){return{start:t,end:n,length:n-t,text:e.value.slice(t,n)}}function l(){return r(document,"body")?document.body:document.getElementsByTagName("body")[0]}var i,u,s,d,f,v,p,m,g,x="undefined";e(document).ready(function(){function h(e,t){var n=e.value,r=i(e),o=r.start;return{value:n.slice(0,o)+t+n.slice(r.end),index:o,replaced:r.text}}function y(e,t){e.focus();var n=i(e);return u(e,n.start,n.end),""==t?document.execCommand("delete",!1,null):document.execCommand("insertText",!1,t),{replaced:n.text,index:n.start}}function T(e,t){e.focus();var n=h(e,t);return e.value=n.value,n}function E(e,t){return function(){var n=this.jquery?this[0]:this,r=n.nodeName.toLowerCase();if(1==n.nodeType&&("textarea"==r||"input"==r&&/^(?:text|email|number|search|tel|url|password)$/i.test(n.type))){var o=[n].concat(Array.prototype.slice.call(arguments)),a=e.apply(this,o);if(!t)return a}return t?this:void 0}}var S=document.createElement("textarea");if(l().appendChild(S),n(S,"selectionStart")&&n(S,"selectionEnd"))i=function(e){var t=e.selectionStart,n=e.selectionEnd;return c(e,t,n)},u=function(e,t,n){var r=a(e,t,n);e.selectionStart=r.start,e.selectionEnd=r.end},g=function(e,t){t?e.selectionEnd=e.selectionStart:e.selectionStart=e.selectionEnd};else{if(!(t(S,"createTextRange")&&r(document,"selection")&&t(document.selection,"createRange")))return l().removeChild(S),void o("No means of finding text input caret position");i=function(e){var t,n,r,o,a=0,l=0,i=document.selection.createRange();return i&&i.parentElement()==e&&(r=e.value.length,t=e.value.replace(/\r\n/g,"\n"),n=e.createTextRange(),n.moveToBookmark(i.getBookmark()),o=e.createTextRange(),o.collapse(!1),n.compareEndPoints("StartToEnd",o)>-1?a=l=r:(a=-n.moveStart("character",-r),a+=t.slice(0,a).split("\n").length-1,n.compareEndPoints("EndToEnd",o)>-1?l=r:(l=-n.moveEnd("character",-r),l+=t.slice(0,l).split("\n").length-1))),c(e,a,l)};var w=function(e,t){return t-(e.value.slice(0,t).split("\r\n").length-1)};u=function(e,t,n){var r=a(e,t,n),o=e.createTextRange(),c=w(e,r.start);o.collapse(!0),r.start==r.end?o.move("character",c):(o.moveEnd("character",w(e,r.end)),o.moveStart("character",c)),o.select()},g=function(e,t){var n=document.selection.createRange();n.collapse(t),n.select()}}l().removeChild(S);var b=function(e,t){var n=h(e,t);try{var r=y(e,t);if(e.value==n.value)return b=y,r}catch(o){}return b=T,e.value=n.value,n};d=function(e,t,n,r){t!=n&&(u(e,t,n),b(e,"")),r&&u(e,t)},s=function(e){u(e,b(e,"").index)},m=function(e){var t=b(e,"");return u(e,t.index),t.replaced};var R=function(e,t,n,r){var o=t+n.length;if(r="string"==typeof r?r.toLowerCase():"",("collapsetoend"==r||"select"==r)&&/[\r\n]/.test(n)){var a=n.replace(/\r\n/g,"\n").replace(/\r/g,"\n");o=t+a.length;var c=t+a.indexOf("\n");"\r\n"==e.value.slice(c,c+2)&&(o+=a.match(/\n/g).length)}switch(r){case"collapsetostart":u(e,t,t);break;case"collapsetoend":u(e,o,o);break;case"select":u(e,t,o)}};f=function(e,t,n,r){u(e,n),b(e,t),"boolean"==typeof r&&(r=r?"collapseToEnd":""),R(e,n,t,r)},v=function(e,t,n){var r=b(e,t);R(e,r.index,t,n||"collapseToEnd")},p=function(e,t,n,r){typeof n==x&&(n=t);var o=i(e),a=b(e,t+o.text+n);R(e,a.index+t.length,o.text,r||"select")},e.fn.extend({getSelection:E(i,!1),setSelection:E(u,!0),collapseSelection:E(g,!0),deleteSelectedText:E(s,!0),deleteText:E(d,!0),extractSelectedText:E(m,!1),insertText:E(f,!0),replaceSelectedText:E(v,!0),surroundSelectedText:E(p,!0)})})}(jQuery);

$().ready(function() {

	$document = $(document);

	$('#form_settings').trigger('reset');

	$document.keyup(function(e) {
		if(e.keyCode === 27) {
			remove_modal();
			$('.addfolder').remove();
			if($('input.rename').length > 0) {
				var input = $('input.rename');
				var old_name = input.attr('data-oldname');
				input.replaceWith('<span class="name">'+old_name+'</span>');
			}
		}

		if(e.keyCode === 113) {

			var name = $('a.folder.active span.name').html();

			$('a.folder.active span.name').replaceWith('<input type="text" value="'+name+'" data-oldname="'+name+'" class="rename">');
			$('input.rename').focus().select();
		}

		console.log(e.keyCode);
	});

	$document.on('click','a.close', function(event) {
		remove_modal();
	});

	if(window.location.hash.length > 0) {
		var section = window.location.hash;
		var section = section.substring(1, section.length);
		load_section(section);
	} else {
		load_section('clients');
	}

	$('body').addClass('fully-loaded');

	$document.on('change', '#multiselect', function(event) {

		var multiselect = $('.multiselect');

		if($('#multiselect').prop('checked') == true)
			multiselect.prop('checked', true);
		else
			multiselect.prop('checked', false);

		multiselect.trigger('change');
					
	});

	$document.on('click', 'table td span.image', function() {

		event.preventDefault();

		$(this).toggleClass('zoom');

		event.stopPropagation();

	});

	$document.on('click', '#attachment-select a.add', function() {

		var id = $(this).attr('data-id');
		var name = $(this).html();

		$('form#message').prepend('<input type="hidden" name="attachment[]" value="'+id+'">');
		$('form#message div.attachments').append('<a class="attachment" href="#" data-id="'+id+'">'+name+'</a>');

		remove_modal();

	});

	$document.on('change', '.multiselect', function() {

		var tr = $(this).parents('tr');

		if($(this).prop('checked') == true)		
			tr.addClass('selected');
		else
			tr.removeClass('selected');

		if($('.multiselect:checked').length > 0 ) {
			$('div.multi span.number').html('('+$('.multiselect:checked').length+')');
			$('div.multi').addClass('show');
		} else {
			$('div.multi').removeClass('show');
		}
					
	});

	$document.on('change','ul.folders input.rename', function(event) {

		event.preventDefault();

		var input = $(this);
		var value = input.val();
		var folder_id = $(input).parents('a').attr('data-id');

		$.ajax({
			url: '/mod/clients/edit-folder.php',
			type: 'POST',
			data: 'value='+value+'&folder_id='+folder_id,
			success: function(msg) {

				var json = JSON.parse(msg);

				if(json[0] == 1) {

					var new_name = `<span class="name">`+value+`</span>`;

					input.replaceWith(new_name);
				}
			}
		});
	
	});

	$document.on('change','ul.folders input.new', function(event) {

		event.preventDefault();

		var input = $(this);
		var value = input.val();

		$.ajax({
			url: '/mod/clients/add-folder.php',
			type: 'POST',
			data: 'value='+value,
			success: function(msg) {

				var json = JSON.parse(msg);

				if(json[0] == 1) {

					var new_folder = `
						<li>
							<a class="folder" href="#" data-name="`+value+` (0)" data-id="`+json[1]+`">
								<span class="name">`+value+`</span>
								<span class="num_files">0 bestanden</span>
							</a>
						</li>
					`;

					input.replaceWith(new_folder);
				}
			}
		});
	
	});

	$document.on('click','a.delete-folder', function(event) {

		event.preventDefault();

		$.ajax({
			url: '/mod/clients/delete-folder.php',
			success: function(msg) {
				$('div.header a.client').click();
			}
		});

	});

	$document.on('click','a.add-folder', function(event) {

		event.preventDefault();

		var new_folder = `
			<li class="addfolder">
				<input type="text" name="name" input class="focus new" placeholder="Naam" />
			</li>
		`;

		$('ul.folders').append(new_folder);

		$('.focus').last().focus();
	
	});

	$document.on('click','span.edit i', function() {

		var span = $(this).parent('span');
		
		var name = span.attr('data-field');
		var value = span.html().replace('<i></i>', '');

		span.replaceWith('<input name="'+name+'" class="dynamic" value="'+value+'">');
	
	});

	$document.on('change','select#multi', function() {

		var type = $('#type').val();
		var checked = $('input.multiselect:checked').map(function(){ return this.value }).get();

		if($(this).val() == 'activate') {

			if(type == 'client') {

				$.ajax({
					url: '/mod/clients/activate-clients.php',
					type: 'POST',
					data: {checked:checked},
					success: function(msg) {
					
						var json = JSON.parse(msg);

						$('select#multi').prop('selectedIndex',0);

						if(json[0] == 1) {
							
							$.each(json[1], function(k,v){
								$('tr#client_'+v).removeClass('inactive');
							});

						}

						$('select#multi').prop('selectedIndex',0);
						$('input#multiselect').prop('checked',false);
						$('input.multiselect:checked').click();
						$('div.multi').removeClass('show');

					}
				});

			}

		}

		if($(this).val() == 'delete') {

			if(type == 'client') {

				$.ajax({
					url: '/mod/clients/delete-clients.php',
					type: 'POST',
					data: {checked:checked},
					success: function(msg) {
					
						var json = JSON.parse(msg);

						$('select#multi').prop('selectedIndex',0);

						if(json[0] == 1) {

							$('div.multi').removeClass('show');
							
							$.each(json[1], function(k,v){
								$('tr#client_'+v).remove();
							});

						}

					}
				});

			}

			if(type == 'file') {

				$.ajax({
					url: '/mod/files/delete-files.php',
					type: 'POST',
					data: {checked:checked},
					success: function(msg) {
					
						var json = JSON.parse(msg);

						$('select#multi').prop('selectedIndex',0);

						if(json[0] == 1) {

							$('div.multi').removeClass('show');
							
							$.each(json[1], function(k,v){
								$('tr#file_'+v).remove();
							});

						}

					}
				});

			}
					
		}

	});

	$document.on('change','input.dynamic', function() {

		var input = $(this);
		var name = input.attr('name');
		var value = input.val();

		$.ajax({
			url: '/mod/clients/update-input.php',
			type: 'POST',
			data: 'name='+name+'&value='+value,
			success: function(msg) {
				if(msg == 1) {
					input.replaceWith('<span class="edit" data-field="'+value+'">'+value+'<i></i></span>');

					if(name == 'company') {
						$('div.header a#companyname').html(value);
					}

				}
			}
		});
	
	});

	$document.on('click','a.add-file', function(e) {

		event.preventDefault();

		var upload = `
			<div class="upload">

				<h2>Bestand(en) uploaden</h2>

				<a class="close" href="#"></a>

				<div class="fileselect">

					<div id="container">
						<p>Sleep bestanden die je wilt uploaden hiernaartoe.</p>
					</div>

					<a id="pickfiles" class="button">Selecteer bestand(en)</a>

				</div>
				<div id="filelist">Your browser doesnt support HTML5 upload.</div>
			</div>`;

		build_modal(upload);

		var uploader = new plupload.Uploader({
			runtimes: 'html5',
			drop_element : 'container',
			browse_button: 'pickfiles',
			url: '/mod/files/upload-chunked.php',
			chunk_size: '10mb',
			max_file_size: '4gb',
			filters: {
				max_file_size: '150mb',
				mime_types: [{title: "files", extensions: "mp4,pdf,doc,docx,txt,zip,rar,mp3,wav,jpg,png,svg"}]
			},
			init: {
				PostInit: function () {
					document.getElementById('filelist').innerHTML = '';
				},
				FilesAdded: function (up, files) {
					$('.fileselect').hide();
					plupload.each(files, function (file) {
						document.getElementById('filelist').innerHTML += `<div id="${file.id}" class="file"><h3>Uploaden..</h3><strong>0%</strong> - ${file.name} (${plupload.formatSize(file.size)})<div class="progress"><div class="inner"></div></div></div>`;
					});
					uploader.start();
				},
				UploadProgress: function (up, file) {

					if(file.percent == 100) {

						$(`#${file.id}`).addClass('complete');
						$(`#${file.id} h3`).html('Klaar');
						document.querySelector(`#${file.id} strong`).innerHTML = `<span>${file.percent}%</span>`;
						$(`#${file.id} .inner`).css('width',`${file.percent}%`);

					} else {

						document.querySelector(`#${file.id} strong`).innerHTML = `<span>${file.percent}%</span>`;
						$(`#${file.id} .inner`).css('width',`${file.percent}%`);

					}

				},
				Error: function (up, err) {
					console.log(err);
				},
				UploadComplete: function(up, files) {

					$.ajax({
						url: '/mod/files/load_folder.php',
						success: function(msg) {
							$('section#dynamic').html(msg);
							remove_modal();
						}
					});
					
				}
			}
		});

		uploader.bind('Init', function(up, params) {

			if (uploader.features.dragdrop) {

				var target = document.getElementById('container');
				
				target.ondragover = function(event) {
					event.dataTransfer.dropEffect = "copy";
				};
				
				target.ondragenter = function() {
					this.className = "dragover";
				};
				
				target.ondragleave = function() {
					this.className = "";
				};
				
				target.ondrop = function() {
					this.className = "";
				};
			}
		});

		uploader.init();

	});

	$document.on('click','a.button', function(e) {

		var button = $(this);

		var posX = e.pageX - button.offset().left
		var posY = e.pageY - button.offset().top;

		$(this).prepend('<span class="pulse" style="margin-left:'+(posX - 10)+'px; margin-top:'+(posY - 10)+'px;"></span>');

		var pulse = $('span.pulse');

		setTimeout(function(){ pulse.remove() }, 500);
	
	});

	$document.on('change','form#fileupload input', function() {

		var form = $('form#fileupload');
		var formData = new FormData(form[0]);

		form.addClass('loading');

		$.ajax({
			url: '/mod/files/upload.php',
			type: 'POST',
			xhr: function() {
				var myXhr = $.ajaxSettings.xhr();
				if(myXhr.upload){
					myXhr.upload.addEventListener('progress',progressHandlingFunction, false);
				}
				return myXhr;
			},
			beforeSend: beforeSendHandler,
			success: function(msg) {
				form.removeClass('loading');
				$('div#files table tbody').prepend(msg);
			},
			data: formData,
			cache: false,
			contentType: false,
			processData: false
		});
	});

	$document.on('click','a.button.attachments', function(event) {

		event.preventDefault();

		$.ajax({
			url: '/mod/clients/load-attachments.php',
			success: function(msg) {
				build_modal(msg);
			}
		});
	
	});

	$document.on('click', 'div#note a.save', function(event){

		event.preventDefault();

		var a = $(this);
		var note = $('div#note textarea').val();
		
		a.addClass('in-progress');

		if(note.length > 0) {

			$.ajax({
				url: '/mod/clients/save-note.php',
				type: 'POST',
				data: 'note='+note,
				success: function(msg) {
					a.removeClass('in-progress');
				}
			});

		}

	});

	$document.on('click', 'div#messages a.send', function(event){

		event.preventDefault();

		var a = $(this);
		var message = $('#new-message').val();

		a.addClass('in-progress');

		if(message.length > 0) {

			$.ajax({
				url: '/mod/clients/add-message.php',
				type: 'POST',
				data: $('form#message').serialize(),
				success: function(msg) {

					if(msg == 1) {
						$('div.header a.client').click();
					}
				}
			});

		}

	});

	$document.on('click', 'a.add-client', function(event){

		event.preventDefault();

		$.ajax({
			url: '/mod/clients/add-client.php',
			success: function(msg) {
				var json = JSON.parse(msg);

				$('section#dynamic').html(json.output);
				$('section.column div.content').html(json.sidebar);
				$('section.main section').hide();
				$('section#dynamic').show();

				$('input.dynamic[name="company"]').focus();
			}
		});

	});

	$document.on('click', 'div.bottom a.button', function(event){

		event.preventDefault();

		$('div.bottom ul').toggleClass('show');

	});

	$document.on('keyup', 'input#search', function(event) {

		event.preventDefault();

		var q = $(this).val();

		if(event.which == 13) {
			propose_search(q, 5);
		} else {
			propose_search(q, 300);
		}

	});

	$document.on('click', 'a.folder', function(event){

		event.preventDefault();

		var folder_id = $(this).attr('data-id');

		$.ajax({
			url: '/mod/files/load_folder.php',
			type: 'POST',
			data: 'folder_id='+folder_id,
			success: function(msg) {

				$('ul.folders a').removeClass('active');
				$('a[data-id="'+folder_id+'"]').addClass('active');

				$('section#dynamic').html(msg)
			}
		});
	});


	$document.on('click', 'table#clients a.edit', function(event){

		event.preventDefault();

		var client_id = $(this).attr('data-id');

		$.ajax({
			url: '/mod/clients/view-client.php',
			type: 'POST',
			data: 'client_id='+client_id,
			success: function(msg) {
				var json = JSON.parse(msg);

				$('section.main section').hide();
				$('section#dynamic').html(json.output);
				$('section.column div.content').html(json.sidebar);
				$('section#dynamic').show();
				var m = $('div.block#messages div.messages');
				m.scrollTop(m.prop('scrollHeight'));
			}
		});
	});

	$document.on('click', 'ul.foldertree a.client', function(event){

		event.preventDefault();

		var client_id = $(this).attr('data-id');

		$.ajax({
			url: '/mod/clients/view-client.php',
			type: 'POST',
			data: 'client_id='+client_id,
			success: function(msg) {

				var json = JSON.parse(msg);

				$('section.main section').hide();
				$('section#dynamic').html(json.output);
				$('section.column div.content').html(json.sidebar);
				$('section#dynamic').show();
				$('ul.menu li a').removeClass('active');
				$('ul.menu li a.clients').addClass('active');
				var m = $('div.block#messages div.messages');
				m.scrollTop(m.prop('scrollHeight'));					
			}
		});
	});

	$('section#files form input').on('change', function() {

		var form = $(this).parent('form');
		var formData = new FormData(form[0]);

		form.addClass('loading');

		$.ajax({
			url: '/mod/files/upload.php',
			type: 'POST',
			xhr: function() {
				var myXhr = $.ajaxSettings.xhr();
				if(myXhr.upload){
					myXhr.upload.addEventListener('progress',progressHandlingFunction, false);
				}
				return myXhr;
			},
			beforeSend: beforeSendHandler,
			success: function(msg) {
				form.removeClass('loading');
				$('section#files table tbody').prepend(msg);
			},
			data: formData,
			cache: false,
			contentType: false,
			processData: false
		});
	});

	$document.on('click', 'a.logout', function(event){

		event.preventDefault();

		$.ajax({
			url: '/ajax/logout.php',
			success: function(msg) {
				location.reload();
			}
		});
	})

	$document.on('click', 'form#forgot a.button', function(event){

		event.preventDefault();

		$('form#forgot').removeClass('error');

		$.ajax({
			url: '/ajax/reset_pass.php',
			type: 'POST',
			data: $('form#forgot').serialize(),
			success: function(msg) {
				if(msg == 1) {
					$('form#forgot').html('<p>Volg de intructies in uw herstel e-mail.</p>')
				} else {
					$('form#forgot').addClass('error');
					setTimeout(function(){ $('form#forgot').removeClass('error'); }, 200);
				}
				
			}
		});
	})
	$document.on('click', 'form#forgot a.cancel', function(event){

		event.preventDefault();

		$('div.form.login').show();
		$('div.form.forgot').hide();

	});

	$document.on('click', 'form#login a.forgot', function(event){

		event.preventDefault();

		$('div.form.login').hide();
		$('div.form.forgot').show();

	});

	$document.on('click', 'form#login a.button', function(event){

		event.preventDefault();

		$('form#login').removeClass('error');

		$.ajax({
			url: '/ajax/login.php',
			type: 'POST',
			data: $('form#login').serialize(),
			success: function(msg) {
				if(msg == 1) {
					location.reload();
				} else {
					$('form#login').addClass('error');
					setTimeout(function(){ $('form#login').removeClass('error'); }, 200);
				}
				
			}
		});
	})

	$('input[type="password"]').keypress(function(e) {
		if(e.which == 13) {
			$('form#login a.button').click();
		}
	});

	$document.on('click', 'a.inactive', function(event) {

		event.preventDefault();
		location.reload();

	});

	$document.on('click', 'ul.menu a', function(event) {

		event.preventDefault();

		//if($(this).hasClass('active')) {
			//location.reload();
		//	} else {
			var section = $(this).attr('data-section');
			load_section(section);
		//}

	});

	$document.on('click', 'div.header a', function(event) {

		event.preventDefault();

	});

	$document.on('click', 'div.header a.client', function(event) {

		event.preventDefault();

		var client_id = $(this).attr('data-id');

		$.ajax({
			url: '/mod/clients/view-client.php',
			type: 'POST',
			data: 'client_id='+client_id,
			success: function(msg) {
				var json = JSON.parse(msg);

				$('section#dynamic').html(json.output);
				$('section.column div.content').html(json.sidebar);
				$('section.main section').hide();
				$('section#dynamic').show();
				var m = $('div.block#messages div.messages');
				m.scrollTop(m.prop('scrollHeight'));
			}
		});

	});

	$document.on('click', 'div.header a.clients', function(event) {

		event.preventDefault();

		$('ul.menu a.clients').addClass('active');
		$('ul.menu a.clients').click();

	});

	$document.on('change', 'textarea[name="images"]', function(event) {

		event.preventDefault();

		var images = $(this).val().split("\n");
		var product_images = '';
		$(this).closest('div.images')

		$.each(images, function(k){
			product_images += '<img src="'+images[k]+'">';
		});

		$(this).parents('div.input').find('div.images').html(product_images);

	});

	$document.on('change', '.setting input, .setting textarea', function(event) {

		event.preventDefault();

		var input = $(this);

		input.removeClass('error');
		input.removeClass('success');

		$.ajax({
			type: 'POST',
			url: '/ajax/update.php',
			data: input.serialize(),
			success: function(msg) {

				console.log(msg);

				if(msg == 1) {
					input.addClass('success');
				} else {
					input.addClass('error');
				}

			}
		});

	});

	$document.on('click', '.editor .controls a', function(event) {

		event.preventDefault();

		var editor = $(this).closest('.editor');
		var textarea = $(editor).find('textarea');
		var preview = $(editor).find('.preview');

		textarea.focus();

		var text = textarea.getSelection().text;

		switch(this.id) {

			case 'header' :
				if(text.length == 0) {
					textarea.replaceSelectedText('## ','collapseToEnd');
				} else {
					if(text[0] == '#' && text[1] == '#') {
						textarea.replaceSelectedText(text.slice(3),'select');
					} else {
						textarea.replaceSelectedText('## '+text,'select');
					}
				}
			break;

			case 'bold' :
				if(text.length == 0) {
					textarea.surroundSelectedText('**','**');
				} else {
					if(text[0] == '*' && text[1] == '*' && text[(text.length-2)] == '*' && text[(text.length-1)] == '*') {
						textarea.replaceSelectedText(text.slice(2, -2),'select');
					} else {
						textarea.replaceSelectedText('**'+text+'**','select');
					}
				}

			break;

			case 'italic' :
				if(text.length == 0) {
					textarea.surroundSelectedText('*','*');
				} else {
					if(text[0] == '*' && text[(text.length-1)] == '*' && (text[1] != '*' || text[2] == '*')) {
						textarea.replaceSelectedText(text.slice(1, -1),'select');
					} else {
						textarea.replaceSelectedText('*'+text+'*','select');
					}
				}
			break;

			case 'strikethrough' :
				if(text.length == 0) {
					textarea.surroundSelectedText('~~','~~');
				} else {
					if(text[0] == '~' && text[1] == '~' && text[(text.length-1)] == '~' && text[(text.length-2)] == '~') {
						textarea.replaceSelectedText(text.slice(2, -2),'select');
					} else {
						textarea.replaceSelectedText('~~'+text+'~~','select');
					}
				}
			break;

			case 'underline' :
				if(text.length == 0) {
					textarea.surroundSelectedText('__','__');
				} else {
					if(text[0] == '_' && text[1] == '_' && text[(text.length-1)] == '_' && text[(text.length-2)] == '_') {
						textarea.replaceSelectedText(text.slice(2, -2),'select');
					} else {
						textarea.replaceSelectedText('__'+text+'__','select');
					}
				}
			break;

			case 'list-ul' :
				if(text.length == 0) {
					textarea.replaceSelectedText('* ','collapseToEnd');
				} else {
					var newtext = '';
					lines = text.split('\n');

					for(var i = 0; i < lines.length; i++)
						newtext = newtext + '* '+lines[i]+'\n';

					textarea.replaceSelectedText(newtext,'select');
				}
			break;

			case 'list-ol' :
				if(text.length == 0) {
					textarea.replaceSelectedText('1. ','collapseToEnd');
				} else {
					var newtext = '';
					lines = text.split('\n');

					for(var i = 0; i < lines.length; i++)
						newtext = newtext + (i+1)+'. '+lines[i]+'\n';

					textarea.replaceSelectedText(newtext,'select');
				}
			break;

			case 'link' :
				if(text.length == 0)
					textarea.replaceSelectedText('[linktekst](https://voorbeeld.nl)','select');
				else
					textarea.surroundSelectedText('['+text+'](https://',')');
			break;

			case 'preview' :

				if($(textarea).is(':visible')) {

					$(this).addClass('code');

					$.ajax({
						type: 'POST',
						url: '/ajax/parsedown.php',
						data: 'text='+$(textarea).val(),
						success: function(msg) {

							preview.html(msg);
							textarea.hide();
							preview.show();
						}
					});

				} else {
					$(this).removeClass('code');
					preview.hide();
					textarea.show();
				}

			break;
		}

		if(this.id != 'preview')
			textarea.trigger('change');

	});

});

var search;

function load_section(section) {

	if(section.length > 0) {

		$('ul.menu a').removeClass('active');
		$('a[data-section="'+section+'"]').addClass('active');

		if(section == 'clients') {

			$.ajax({
				url: '/mod/clients/overview.php',
				success: function(msg) {
					var json = JSON.parse(msg);
					$('section#dynamic').html(json.output);
					$('section.column div.content').html(json.sidebar);
					$('section#dynamic').show();
				}
			});

		}

		if(section == 'files') {

			$.ajax({
				url: '/mod/files/overview.php',
				success: function(msg) {
					var json = JSON.parse(msg);
					$('section#dynamic').html(json.output);
					$('section.column div.content').html(json.sidebar);
					$('section#dynamic').show();
				}
			});

		}

		if(section == 'settings') {

			$.ajax({
				url: '/settings.php',
				success: function(msg) {
					var json = JSON.parse(msg);
					$('section#dynamic').html(json.output);
					$('section.column div.content').html(json.sidebar);
					$('section#dynamic').show();
				}
			});

		}

		window.location.hash = section;

	}

}

function edit_file(file_id) {

	$.ajax({
		type: 'POST',
		url: '/mod/files/edit-file.php',
		data: 'file_id'+file_id,
		success: function(msg) {
			build_modal(msg);
		}
	});
}

function build_modal(content) {

	var modal = $('body div.modal');

	if(modal.length > 0) {
		modal.removeClass('visible');
		modal.html(content);
	} else {
		$('body').prepend('<div class="modal">'+content+'</div>');
	}
	setTimeout(function(){ $('div.modal').addClass('visible'); }, 200);
}

function remove_modal() {

	$('body div.modal').addClass('removed');

	setTimeout(function(){ $('body div.modal').remove(); }, 400);
}

function beforeSendHandler(e) { }

function errorHandler(e) { }

function progressHandlingFunction(e) {
	if(e.lengthComputable){
		$('progress').attr({value:e.loaded,max:e.total});
	}
}

function propose_search(q,timeout) {

	clearTimeout(search);

	search = setTimeout(function(){

		$('section.main section').hide();

		if(q.length > 0) {
			$('section#dynamic').html('<div class="header"><h2>Zoekterm: <span>'+q+'</span></h2></div>');
		} else {
			$('section#dynamic').html('<div class="header"><h2>Vul een zoekterm in</h2></div>');
		}
		
		$('section#dynamic').show();

		$.ajax({
			url: '/mod/clients/search.php',
			type: 'POST',
			data: 'q='+q,
			success: function(msg) {
				if(msg.length > 0)
					$('section#dynamic').append(msg);
				else
					$('section#dynamic').append('<p>Geen klanten gevonden</p>');
			}
		});

		$.ajax({
			url: '/mod/files/search.php',
			type: 'POST',
			data: 'q='+q,
			success: function(msg) {
				if(msg.length > 0)
					$('section#dynamic').append(msg);
				else
					$('section#dynamic').append('<p>Geen bestanden gevonden</p>');
			}
		});

	}, timeout);

}