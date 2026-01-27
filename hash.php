<?php
echo password_hash('rahasia123', PASSWORD_DEFAULT);
echo "\n";
echo (password_verify("Rahasia",'$2y$10$7t8Y0B5p1SRXFVDqJlsFm.eBiwxSDJjHWOuaZGWYilEhf8G6vN0oi')) ? "password sesuai" : "password tidak sesuai";