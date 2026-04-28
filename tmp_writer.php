<?php
\$content = file_get_contents('C:/laragon/www/ces-legal/tmp_src.txt');
file_put_contents('C:/laragon/www/ces-legal/app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php', \$content);
echo 'Written ok';
