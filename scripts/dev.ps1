<#
.SYNOPSIS
    Démarre l'environnement de développement CESIZen : base de données puis serveur web.

.DESCRIPTION
    La base de développement est un conteneur MySQL 8.4, même moteur que la
    chaîne d'intégration et la production — principe de parité des
    environnements (voir docs/adr/0001).

    Elle est publiée sur le port 3307 et non 3306 : le poste héberge aussi un
    MySQL XAMPP servant d'autres projets, que ce script ne touche pas.

    Ce script ne concerne que le poste de développement. La production est
    décrite par compose.prod.yaml.

.EXAMPLE
    .\scripts\dev.ps1
    .\scripts\dev.ps1 -Port 8001
    .\scripts\dev.ps1 -SkipDatabase      # base déjà démarrée par ailleurs
#>

[CmdletBinding()]
param(
    [int]    $Port          = 8000,
    [string] $Conteneur     = 'cesizen-mysql84',
    [int]    $PortBase      = 3307,
    [switch] $SkipDatabase
)

$ErrorActionPreference = 'Stop'
$projet = Split-Path -Parent $PSScriptRoot

function Etape  { param([string] $Message) Write-Host "==> $Message" -ForegroundColor Cyan }
function Bon    { param([string] $Message) Write-Host "    $Message" -ForegroundColor Green }
function Alerte { param([string] $Message) Write-Host "    $Message" -ForegroundColor Yellow }
function Fatal  { param([string[]] $Lignes) $Lignes | ForEach-Object { Write-Host "    $_" -ForegroundColor Red }; exit 1 }

function Test-Port {
    param([int] $Numero)
    $client = New-Object Net.Sockets.TcpClient
    try   { $client.Connect('127.0.0.1', $Numero); return $true }
    catch { return $false }
    finally { $client.Dispose() }
}

# --- 1. Configuration locale -------------------------------------------------
Etape 'Vérification de la configuration locale'

foreach ($fichier in @('.env.local', '.env.test.local')) {
    if (-not (Test-Path (Join-Path $projet $fichier))) {
        Fatal @(
            "$fichier est absent.",
            'Copiez .env.local.example vers .env.local et renseignez-le.',
            '.env.test.local doit reprendre le même DATABASE_URL : Symfony ne lit',
            'pas .env.local en environnement de test.'
        )
    }
}
Bon 'Fichiers .env.local et .env.test.local présents'

# --- 2. Base de données ------------------------------------------------------
if (-not $SkipDatabase) {
    Etape "Base de données (MySQL 8.4, port $PortBase)"

    if (Test-Port $PortBase) {
        Bon "La base répond déjà sur le port $PortBase"
    }
    else {
        # Pas de redirection 2>&1 sur un exécutable natif : en PowerShell 5.1
        # chaque ligne de stderr deviendrait une ErrorRecord, donc fatale sous
        # ErrorActionPreference = Stop. On se fie au code de retour.
        docker info --format '{{.ServerVersion}}' | Out-Null
        if ($LASTEXITCODE -ne 0) {
            Fatal @('Le démon Docker ne répond pas. Démarrez Docker Desktop puis relancez.')
        }

        $existe = (docker ps -a --filter "name=^/$Conteneur$" --format '{{.Names}}')
        if (-not $existe) {
            Fatal @(
                "Le conteneur $Conteneur n'existe pas.",
                'Créez-le une fois pour toutes :',
                '',
                "  docker run -d --name $Conteneur --restart unless-stopped ``",
                "    -p 127.0.0.1:${PortBase}:3306 ``",
                '    -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=projet_solo ``',
                '    -e MYSQL_USER=cesizen -e MYSQL_PASSWORD=cesizen ``',
                '    -v cesizen_mysql84_data:/var/lib/mysql ``',
                '    mysql:8.4 --character-set-server=utf8mb4 ``',
                '    --collation-server=utf8mb4_unicode_ci'
            )
        }

        Write-Host '    Démarrage du conteneur...'
        docker start $Conteneur | Out-Null

        $limite = (Get-Date).AddSeconds(60)
        while ((Get-Date) -lt $limite) {
            # MYSQL_PWD évite l'avertissement « password on the command line »
            # que PowerShell remonterait comme une erreur.
            docker exec $Conteneur sh -c 'MYSQL_PWD=root mysqladmin ping -h127.0.0.1 -uroot --silent' | Out-Null
            if ($LASTEXITCODE -eq 0) { break }
            Start-Sleep -Milliseconds 500
        }

        if ($LASTEXITCODE -eq 0) { Bon "MySQL 8.4 écoute sur le port $PortBase" }
        else { Fatal @('La base n a pas démarré dans le délai imparti.', "Journal : docker logs $Conteneur") }
    }
}

# --- 3. Serveur web ----------------------------------------------------------
Etape 'Serveur web'

if (Test-Port $Port) {
    Alerte "Le port $Port est déjà occupé — un serveur tourne probablement déjà."
    Alerte "Relancez avec un autre port : .\scripts\dev.ps1 -Port 8001"
    exit 1
}

Push-Location $projet
try {
    Write-Host '    Vidage du cache...'
    & php bin/console cache:clear --quiet

    Bon "Application disponible sur http://127.0.0.1:$Port"
    Write-Host '    Ctrl+C pour arrêter (la base, elle, continue de tourner).' -ForegroundColor DarkGray
    Write-Host ''

    & php -S "127.0.0.1:$Port" -t public public/index.php
}
finally {
    Pop-Location
}
