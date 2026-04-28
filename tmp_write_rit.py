target = "C:/laragon/www/ces-legal/app/Filament/Admin/Resources/ReglamentoInternoResource/Pages/CreateReglamentoInterno.php"
with open("C:/laragon/www/ces-legal/tmp_rit_content.php", "r", encoding="utf-8") as f:
    content = f.read()
with open(target, "w", encoding="utf-8", newline="\n") as f:
    f.write(content)
print("Written ok")
