<#
.SYNOPSIS
    Démarre l'environnement de développement CESIZen : base de données puis serveur web.

.DESCRIPTION
    Le poste de développement utilise le MySQL de XAMPP, qui n'est pas enregistré
    comme service Windows : il faut le lancer à la main à chaque ouverture de
    session. Ce script s'en charge, vérifie que la configuration locale est en
    place, puis démarre le serveur PHP intégré.

    La production, elle, tourne sous Docker (voir compose.prod.yaml) : ce script
    ne concerne que le poste de développement.

.EXAMPLE
    .\scripts\dev.ps1
    .\scripts\dev.ps1 -Port 8001
    .\scripts\dev.ps1 -SkipDatabase      # MySQL déjà démarré par ailleurs
#>

[CmdletBinding()]
param(
    [int]    $Port         = 8000,
    [string] $MysqlHome    = 'C:\xampp\mysql',
    [switch] $SkipDatabase
)

$ErrorActionPreference = 'Stop'
$projet = Split-Path -Parent $PSScriptRoot

function Etape { param([string] $Message) Write-Host "==> $Message" -ForegroundColor Cyan }
function Bon   { param([string] $Message) Write-Host "    $Message" -ForegroundColor Green }
function Alerte{ param([string] $Message) Write-Host "    $Message" -ForegroundColor Yellow }

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
    $chemin = Join-Path $projet $fichier
    if (-not (Test-Path $chemin)) {
        Write-Host "    $fichier est absent." -ForegroundColor Red
        Write-Host "    Copiez .env.local.example vers .env.local et renseignez-le" -ForegroundColor Red
        Write-Host "    (.env.test.local doit reprendre le même DATABASE_URL :" -ForegroundColor Red
        Write-Host "     Symfony ne lit pas .env.local en environnement de test)." -ForegroundColor Red
        exit 1
    }
}
Bon 'Fichiers .env.local et .env.test.local présents'

# --- 2. Base de données ------------------------------------------------------
if (-not $SkipDatabase) {
    Etape 'Base de données'

    if (Test-Port 3306) {
        Bon 'MySQL répond déjà sur le port 3306'
    }
    else {
        $mysqld = Join-Path $MysqlHome 'bin\mysqld.exe'
        if (-not (Test-Path $mysqld)) {
            Write-Host "    mysqld.exe introuvable dans $MysqlHome." -ForegroundColor Red
            Write-Host "    Indiquez le bon chemin : .\scripts\dev.ps1 -MysqlHome 'C:\chemin\mysql'" -ForegroundColor Red
            exit 1
        }

        Write-Host '    Démarrage de MySQL...'
        Start-Process -FilePath $mysqld `
                      -ArgumentList "--defaults-file=$MysqlHome\bin\my.ini", '--standalone' `
                      -WindowStyle Hidden

        $limite = (Get-Date).AddSeconds(30)
        while ((Get-Date) -lt $limite -and -not (Test-Port 3306)) { Start-Sleep -Milliseconds 500 }

        if (Test-Port 3306) { Bon 'MySQL écoute sur le port 3306' }
        else {
            Write-Host '    MySQL n a pas démarré dans le délai imparti.' -ForegroundColor Red
            Write-Host "    Consultez le journal : $MysqlHome\data\*.err" -ForegroundColor Red
            exit 1
        }
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
    Write-Host '    Ctrl+C pour arrêter (MySQL, lui, continue de tourner).' -ForegroundColor DarkGray
    Write-Host ''

    & php -S "127.0.0.1:$Port" -t public public/index.php
}
finally {
    Pop-Location
}
