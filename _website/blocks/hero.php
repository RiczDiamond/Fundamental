<?php

$headline = (string) ($section['fields']['headline'] ?? 'Wij bouwen snelle websites');
$subline = (string) ($section['fields']['subline'] ?? 'Met <span id="html5">HTML5</span>, <span id="css">CSS</span>, <span id="javascript">JavaScript</span> en <span id="php">PHP</span> ontwikkelen wij moderne en snelle websites.');

component_section_open('section-hero');

?>

<div class="container">
<h1><?php echo $headline; ?></h1>

<p>
    <?php echo $subline; ?>
</p>

</div>


<?php

component_section_close();