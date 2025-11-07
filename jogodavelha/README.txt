Jogo da Velha (PHP) - Multiplayer simples via HTTP polling

Como usar (XAMPP/WAMP/LAMP):
1) Coloque a pasta "tictactoe-php" no diretório público do seu servidor (ex.: htdocs ou www).
   Estrutura:
   - public/ (front-end)
   - backend/api.php (backend)
   - backend/data/rooms (persistência simples em JSON)
2) Acesse: http://SEU_SERVIDOR/tictactoe-php/public/
3) Clique em "Criar sala" (você será o X) e compartilhe o código com outra pessoa.
4) A outra pessoa abre a mesma URL, digita o código e entra (será o O).

Observações:
- Este projeto usa polling (requisições periódicas) a cada ~1.2s para sincronizar o estado.
  Funciona em hospedagens comuns sem extensões.
- Os estados das salas são armazenados como JSON em backend/data/rooms.
  Em produção, considere proteger essa pasta via .htaccess ou mover para fora do webroot.
- Para "limpeza" de salas antigas, você pode criar um cron que apague arquivos mais antigos que X horas.
