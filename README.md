# INTEGRAEMPHP o PAGCOMPLETO 

Este projeto é uma solução PHP para integrar um sistema de e-commerce com o gateway de pagamento PAGCOMPLETO. Ele automatiza o processamento de pedidos elegíveis, gerencia o status no banco de dados e oferece uma interface web simples para monitoramento e controle.

## Objetivo do Projeto

O principal objetivo deste projeto é demonstrar a capacidade de integrar um sistema de pedidos com um gateway de pagamento externo, o PAGCOMPLETO. A solução foca na automação do processo de verificação e atualização do status de pagamentos de pedidos, garantindo a consistência dos dados e a segurança das operações.

## Contexto do Desafio
Um cliente acabou de contratar o nosso gateway de pagamento PAGCOMPLETO. A missão será integrar a API para que os pedidos possam ser processados corretamente, seguindo os critérios técnicos definidos.

## Características Principais

-  **Processamento Automático**: O sistema pode ser configurado para processar pedidos elegíveis automaticamente (via cron job, por exemplo, embora a funcionalidade de timer não esteja implementada diretamente no código PHP, a arquitetura permite isso).
-  **Interface Web Simples**: Um dashboard intuitivo para monitoramento e controle manual das operações.
-  **Reset de Banco de Dados**: Funcionalidade para restaurar o banco de dados aos seus dados originais, ideal para ambientes de desenvolvimento e teste.
-  **Validação Rigorosa**: Verificação e normalização de dados antes do envio para a API externa.
-  **Tratamento de Erros**: Gestão robusta de falhas e exceções durante a comunicação com a API e operações de banco de dados.
-  **Modo de Desenvolvimento Seguro**: Permite testar toda a lógica de integração sem realizar chamadas reais à API do PAGCOMPLETO, protegendo o ambiente externo.

## Arquitetura do Software

Este projeto segue uma arquitetura cliente-servidor tradicional, onde o PHP atua como a camada de aplicação, interagindo com um banco de dados PostgreSQL e um servidor web Apache. A comunicação com a API externa é feita via cURL.

### Tecnologias Utilizadas:

-   **PHP 8.1+**: Linguagem de programação backend, com foco no uso de PDO (PHP Data Objects) para interação segura com o banco de dados, prevenindo ataques de SQL Injection.
-   **PostgreSQL 14+**: Sistema de gerenciamento de banco de dados relacional (DBServer), utilizado para armazenar os dados de pedidos, clientes, pagamentos e configurações.
-   **Apache 2.4+**: Servidor web (WebServer) responsável por servir as páginas PHP e gerenciar as requisições HTTP.
-   **HTML5/CSS3/JavaScript (Bootstrap)**: Utilizados para a construção da interface de usuário (frontend), garantindo um design responsivo e intuitivo. O Bootstrap é empregado para agilizar o desenvolvimento da UI.
-   **cURL**: Biblioteca utilizada para realizar requisições HTTP de forma programática, sendo a ferramenta escolhida para a comunicação com a API do PAGCOMPLETO.

### Método de Segurança:

-   **PDO com Prepared Statements**: Essencial para prevenir SQL Injection, garantindo que os dados inseridos ou consultados no banco de dados sejam tratados de forma segura.
-   **Validação dos Dados**: Implementação de validações para garantir a integridade e o formato correto dos dados antes do processamento.
-   **Tratativa de Erros**: Mecanismos robustos para lidar com exceções e falhas, tanto nas operações de banco de dados quanto na comunicação com a API.
-   **Logs sem Exposição de Dados Sensíveis**: As mensagens de log são projetadas para não expor informações confidenciais, protegendo a privacidade dos dados.

## Funcionalidades Nucleares (Main Features)

O sistema oferece as seguintes funcionalidades principais:

-   **Reset do Banco de Dados**: Um botão na interface web permite recarregar o banco de dados com os dados originais de teste, facilitando o desenvolvimento e a depuração.
-   **Dashboard**: Uma interface web que lista todos os pedidos do banco de dados, exibindo informações essenciais como ID do pedido, cliente, valor total, forma de pagamento, status e se o pedido é elegível para processamento pelo PAGCOMPLETO.
-   **Processamento Manual**: Um botão na dashboard permite iniciar o processamento de pedidos elegíveis sob demanda, enviando-os para a API do PAGCOMPLETO.
-   **Sistema de Mensagens**: Exibição de mensagens de feedback claras e concisas sobre o status das operações (sucesso, aviso, erro) diretamente na interface.
-   **Teste de Conexão**: Verificação automática da conectividade com o banco de dados na inicialização da dashboard.

## Critérios de Elegibilidade

Para que um pedido seja considerado elegível para processamento pelo gateway PAGCOMPLETO, ele deve atender aos seguintes critérios:

1.  **Gateway PAGCOMPLETO**: Apenas lojas que utilizam o `id_gateway = 1` (PAGCOMPLETO).
2.  **Cartão de Crédito**: Apenas pagamentos realizados via `id_formapagto = 3` (Cartão de Crédito).
3.  **Aguardando Pagamento**: O status do pedido deve ser `id_situacao = 1` (Aguardando Pagamento).

Após o processamento pela API, o status do pedido no banco de dados será atualizado conforme o retorno da API (Pagamento Identificado ou Pedido Cancelado).

## ⚙️ Instalação e Configuração

Este guia detalha os passos para configurar o ambiente necessário e implantar o projeto em um servidor Ubuntu com Apache2 e PostgreSQL.

### Pré-requisitos

Certifique-se de ter acesso `sudo` no seu servidor Ubuntu.

### 1. Instalação de Pacotes Essenciais

Primeiro, atualize os pacotes do sistema e instale o PostgreSQL, Apache2 e os módulos PHP necessários:

```bash
sudo apt update
sudo apt install -y postgresql postgresql-contrib apache2 php libapache2-mod-php php-pgsql php-curl php-json
```

### 2. Configuração do Apache2

Crie o diretório do projeto no Apache e ajuste as permissões:

```bash
sudo systemctl start apache2
sudo systemctl enable apache2
sudo mkdir -p /var/www/html/pagcompleto
sudo chown -R $USER:$USER /var/www/html/pagcompleto
```

**Observação**: `$USER` deve ser substituído pelo seu usuário no servidor (ex: `ubuntu`).

### 3. Configuração do PostgreSQL

Inicie o serviço PostgreSQL e configure o banco de dados e o usuário para o projeto:

```bash
sudo systemctl start postgresql
sudo systemctl enable postgresql
sudo -i -u postgres psql -c "ALTER USER postgres WITH PASSWORD 'postgres';"
sudo -i -u postgres psql -c "CREATE DATABASE pagcompleto_db;"
sudo -i -u postgres psql -c "CREATE USER pagcompleto_user WITH ENCRYPTED PASSWORD 'pagcompleto_password';"
sudo -i -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE pagcompleto_db TO pagcompleto_user;"

## Conceder permissões adicionais para o usuário do banco de dados
## (Necessário para operações como o reset do banco, que manipulam schemas e tabelas) - Não recomendado!
## A forma como implementei o reset de tabelas requer permissões de superuser. Existem formas mais apropriadas.
sudo -u postgres psql -d pagcompleto_db -c "GRANT USAGE, CREATE ON SCHEMA public TO pagcompleto_user; ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO pagcompleto_user; ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO pagcompleto_user;"
```

### 4. Implantação do Projeto

1.  **Copie os arquivos do projeto** (config.php, index.php, process.php, reset.php) para o diretório `/var/www/html/pagcompleto/`.
2.  **Crie o diretório `sql/`** dentro de `/var/www/html/pagcompleto/` e copie todos os arquivos `.sql` fornecidos para dentro dele.

    ```bash
    sudo mkdir -p /var/www/html/pagcompleto/sql
    # Copie seus arquivos .sql para este diretório
    # Exemplo: sudo cp /caminho/para/seus/arquivos/*.sql /var/www/html/pagcompleto/sql/
    ```

3.  **Ajuste as permissões** dos arquivos PHP:

    ```bash
    sudo chown -R www-data:www-data /var/www/html/pagcompleto
    sudo chmod -R 755 /var/www/html/pagcompleto
    ```

### 5. Configuração do PHP

Para gerar verbose quanto para o PHP (e demonstrar melhor quaisquer erros com mais detalhes):
Verifique se o `php.ini` está configurado corretamente para exibir erros durante o desenvolvimento:

```bash
sudo nano /etc/php/8.1/apache2/php.ini # ou a versão do seu PHP
```

Procure por:

```ini
display_errors = Off
```

E mude para:

```ini
display_errors = On
```

Salve e feche o arquivo, e reinicie o Apache:

```bash
sudo systemctl restart apache2
```


## Estrutura de Arquivos

O projeto possui uma estrutura de arquivos simples e direta, facilitando a compreensão e manutenção:

```
pagcompleto/
├── config.php
├── index.php
├── process.php
├── reset.php
└── sql/
    ├── clientes.sql
    ├── formas_pagamento.sql
    ├── gateways.sql
    ├── lojas_gateway.sql
    ├── pedido_situacao.sql
    ├── pedidos.sql
    └── pedidos_pagamentos.sql
```

-   `config.php`: Contém as configurações de conexão com o banco de dados, credenciais da API e o modo de desenvolvimento.
-   `index.php`: A interface web principal (dashboard) que exibe a lista de pedidos e os botões de ação.
-   `process.php`: Script responsável por buscar pedidos elegíveis, construir o payload da API, enviar a requisição e atualizar o banco de dados.
-   `reset.php`: Script para resetar o banco de dados, recarregando as tabelas e dados iniciais a partir dos arquivos SQL.
-   `sql/`: Diretório que contém os scripts SQL para criação das tabelas e inserção dos dados de teste.


## 🚀 Como Usar

Após a instalação e configuração, siga os passos abaixo para interagir com o sistema:

1.  **Acesse a Dashboard**: Abra seu navegador e navegue para `http://seu-servidor-ip-ou-dominio/pagcompleto/`.

2.  **Resetar o Banco de Dados**: É necessário resetar o Banco antes de processar os dados para de fato observar o funcionamento. Clique no botão **"Resetar Banco de Dados"** na dashboard. Isso irá:
    -   Limpar todas as tabelas existentes.
    -   Recarregar os dados a partir dos arquivos `.sql` na pasta `sql/`.
    -   Você verá uma mensagem de sucesso na tela.

3.  **Visualizar Pedidos**: Após o reset, a dashboard exibirá uma lista de pedidos. Os pedidos elegíveis para processamento (que atendem aos critérios de elegibilidade) serão destacados.

4.  **Processar Pedidos Elegíveis**: Clique no botão **"Processar Pedidos Elegíveis"** na dashboard. O sistema irá:
    -   Buscar todos os pedidos que atendem aos critérios de elegibilidade.
    -   Para cada pedido, simular (em modo de desenvolvimento) ou enviar (em modo de produção) uma requisição para a API do PAGCOMPLETO.
    -   Atualizar o status do pedido no banco de dados com base na resposta da API.
    -   Você verá uma mensagem de sucesso ou erro na tela.

5.  **Modo de Desenvolvimento**: Por padrão, o projeto está configurado com `DEVELOPMENT_MODE = true` no `config.php`. Isso significa que as chamadas para a API do PAGCOMPLETO são simuladas, permitindo testar a lógica sem afetar o ambiente real. Para desativar o modo de desenvolvimento e realizar chamadas reais, altere `DEVELOPMENT_MODE` para `false` no `config.php`.

## Considerações de Segurança

-   **Prevenção de SQL Injection**: O uso de PDO (PHP Data Objects) com *prepared statements* é fundamental para proteger o banco de dados contra ataques de SQL Injection. Isso garante que as consultas SQL sejam construídas de forma segura, separando o código SQL dos dados fornecidos pelo usuário.
-   **Validação e Sanitização de Entradas**: Embora o projeto atual lide principalmente com dados internos do banco, em um cenário real, todas as entradas de usuário (formulários, parâmetros de URL) devem ser rigorosamente validadas e sanitizadas para prevenir ataques como Cross-Site Scripting (XSS) e outros.
-   **Tratamento de Erros Controlado**: Mensagens de erro detalhadas são úteis para depuração, mas não devem ser expostas diretamente ao usuário final em ambiente de produção. O projeto utiliza `error_reporting(E_ALL)` e `ini_set("display_errors", 1)` para desenvolvimento, mas isso deve ser desativado em produção (`display_errors = Off`) para evitar vazamento de informações sensíveis.
-   **Credenciais Seguras**: As credenciais de banco de dados e tokens de API são armazenados no `config.php`. Para ambientes de produção, é altamente recomendável utilizar variáveis de ambiente ou um sistema de gerenciamento de segredos para armazenar essas informações, evitando que elas fiquem diretamente no código-fonte.
-   **Modo de Desenvolvimento**: O `DEVELOPMENT_MODE` garante que nenhuma transação real seja enviada para a API externa durante o desenvolvimento e teste, protegendo o ambiente de produção de dados indesejados ou chamadas acidentais.
-   **Transações de Banco de Dados**: As operações de atualização de status de pedidos são encapsuladas em transações de banco de dados. Isso garante a atomicidade das operações: ou todas as alterações são aplicadas com sucesso, ou nenhuma delas é, mantendo a integridade dos dados mesmo em caso de falhas.

## 🛠️ Desenvolvimento e Depuração

Para desenvolvedores que desejam estender ou depurar o projeto, as seguintes dicas são úteis:

-   **Exibição de Erros**: Certifique-se de que `display_errors = On` e `error_reporting = E_ALL` estejam configurados no seu `php.ini` (e no topo de cada arquivo PHP) durante o desenvolvimento para ver todos os avisos e erros. Lembre-se de desativá-los em produção.
-   **Modo de Desenvolvimento**: Mantenha `DEVELOPMENT_MODE = true` no `config.php` para simular as respostas da API e evitar chamadas reais durante o desenvolvimento.
-   **Logs de Erro**: Verifique os logs de erro do Apache (`/var/log/apache2/error.log`) e e do módulo PHP para mensagens detalhadas sobre falhas de execução.
-   **Depuração de Banco de Dados**: Utilize ferramentas como `psql` ou um cliente gráfico (ex: DBeaver) para inspecionar o estado do banco de dados e verificar se as operações estão sendo realizadas corretamente.
-   **Comentários no Código**: O código está comentado através para facilitar o entendimento das funcionalidades e decisões.

---

Desenvolvido em Agosto de 2025 por Henrique Stanke Scandelari como parte de um teste técnico para uma vaga de Programador PHP Júnior.

Godspeed!
>>>>>>> 6a6139c (Commit Inicial)
