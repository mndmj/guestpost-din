# Run: & ./wp-content/themes/hello-elementor-child/tests/auth-styles-smoke.ps1 -BaseUrl <Studio site URL>
param([Parameter(Mandatory = $true)][uri]$BaseUrl)

$ErrorActionPreference = 'Stop'
$taskCases = @(
    @{ Path = '/my-account/'; Form = 'woocommerce-form-login' },
    @{ Path = '/wp-login.php'; Form = 'id="loginform"' }
)

foreach ($taskCase in $taskCases) {
    $taskUrl = [uri]::new($BaseUrl, $taskCase.Path)
    $taskResponse = Invoke-WebRequest -Uri $taskUrl -UseBasicParsing
    $taskHtml = $taskResponse.Content
    $taskLinks = [regex]::Matches($taskHtml, '<link\b[^>]*\bid=["'']gpm-auth-style-css["''][^>]*>')

    if ($taskLinks.Count -ne 1 -or -not $taskHtml.Contains($taskCase.Form)) {
        throw "Expected a login form and exactly one shared stylesheet at $taskUrl"
    }

    $taskHref = [regex]::Match($taskLinks[0].Value, '\bhref=["'']([^"'']+)["'']').Groups[1].Value
    $taskCssUrl = [uri]::new($taskUrl, [System.Net.WebUtility]::HtmlDecode($taskHref))
    $taskCss = (Invoke-WebRequest -Uri $taskCssUrl -UseBasicParsing).Content
    if (-not $taskCss.Contains('form.woocommerce-form-login') -or -not $taskCss.Contains('form#loginform')) {
        throw "Shared stylesheet is missing WooCommerce or WordPress login rules at $taskUrl"
    }

    Write-Output "PASS $($taskCase.Path): login form + shared CSS loaded once"
}
