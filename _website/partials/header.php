<!doctype html>

<html lang="nl">



<head>
	<title>Ontbrand - Brandschone Online Techniek</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="viewport" content="width=device-width">
	<link href="/css/style.css" rel="stylesheet" type="text/css" media="all">
	<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap" rel="stylesheet"> 

	<link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
	<link rel="icon" href="favicon.ico">
	<link rel="manifest" href="site.webmanifest">
	<link rel="mask-icon" href="safari-pinned-tab.svg" color="#f1113b">
	<meta name="msapplication-TileColor" content="#f1113b">
	<meta name="theme-color" content="#f1113b">
</head>

<body>
<div class="bar first"></div>
<div class="bar middle"></div>
<div class="bar last"></div>

	<div class="page">

		<header>

			<a href="/" class="logo">

				<?php echo file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/logo/logomol.svg'); ?>					

            </a>
			<div id="tagline" class="tagline">Brandschone Online Techniek</div>
			<div id="menu" class="collapsed">
				<a href="#" class="menu">
					<div id="loading">
						<svg width="38" height="38" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" stroke="#fff">
							<g fill="none" fill-rule="evenodd">
								<g transform="translate(1 1)" stroke-width="2">
									<circle stroke-opacity=".5" cx="18" cy="18" r="18"/>
									<path d="M36 18c0-9.94-8.06-18-18-18">
										<animateTransform
											attributeName="transform"
											type="rotate"
											from="0 18 18"
											to="360 18 18"
											dur="1s"
											repeatCount="indefinite"/>
									</path>
								</g>
							</g>
						</svg>
					</div>
				</a>
				<ul>
					<li><a href="/" data-page="home">Home</a></li>
					<li><a href="/werk/" data-page="werk">Werk</a></li>
					<li><a href="/diensten/" data-page="diensten">Diensten</a></li>
					<li><a href="/vacatures/" data-page="vacatures">Vacatures<span class="number">4</span></a></li>
					<li><a href="/contact/" data-page="contact">Contact</a></li>
				</ul>
			</div>

			<ul class="language">
				<li><a href="/" class="active" data-language="NL">NL</a></li>
				<li><a href="/" data-language="EN">EN</a></li>
			</ul>


			<ul class="contact">
				<li><a href="mailto:info@ontbrand.com" class="email">E-mail<span>info@ontbrand.com</span></a></li>
				<li><a href="https://klantenportal.ontbrand.com">Klantenportal<span>Inloggen</span></a></li>
			</ul>

			

		</header>

		<div id="content">