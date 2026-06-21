Add-Type -AssemblyName System.Drawing

$outDir = "C:\Users\user\Downloads\UniServerZ\www\cocopay\assets\images\logoIcon"
$brand = "NATCODEV"
$name = "COCONUT FARMERS"
$suffix = "CO-OPERATIVE SOCIETY"

function New-LogoBitmap {
    param(
        [string]$Path,
        [bool]$Dark
    )

    $width = 1400
    $height = 330
    $bitmap = New-Object System.Drawing.Bitmap $width, $height
    $bitmap.SetResolution(144, 144)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
    $graphics.Clear([System.Drawing.Color]::Transparent)

    $green = [System.Drawing.Color]::FromArgb(22, 118, 64)
    $deep = [System.Drawing.Color]::FromArgb(27, 58, 36)
    $lime = [System.Drawing.Color]::FromArgb(148, 194, 70)
    $gold = [System.Drawing.Color]::FromArgb(242, 181, 66)
    $brown = [System.Drawing.Color]::FromArgb(112, 75, 44)
    $text = if ($Dark) { [System.Drawing.Color]::White } else { $deep }
    $muted = if ($Dark) { [System.Drawing.Color]::FromArgb(224, 246, 226) } else { [System.Drawing.Color]::FromArgb(79, 104, 82) }

    $greenBrush = New-Object System.Drawing.SolidBrush $green
    $deepBrush = New-Object System.Drawing.SolidBrush $deep
    $limeBrush = New-Object System.Drawing.SolidBrush $lime
    $goldBrush = New-Object System.Drawing.SolidBrush $gold
    $brownPen = New-Object System.Drawing.Pen $brown, 10
    $greenPen = New-Object System.Drawing.Pen $green, 8
    $limePen = New-Object System.Drawing.Pen $lime, 7
    $textBrush = New-Object System.Drawing.SolidBrush $text
    $mutedBrush = New-Object System.Drawing.SolidBrush $muted

    $markBg = New-Object System.Drawing.Drawing2D.GraphicsPath
    $markBg.AddEllipse(24, 34, 210, 210)
    $graphics.FillPath((New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb(236, 250, 226))), $markBg)
    $graphics.DrawPath((New-Object System.Drawing.Pen ([System.Drawing.Color]::FromArgb(190, 222, 156), 5)), $markBg)

    $trunk = New-Object System.Drawing.Drawing2D.GraphicsPath
    $trunk.AddBezier(126, 210, 120, 166, 132, 127, 146, 96)
    $graphics.DrawPath($brownPen, $trunk)

    $leaf1 = New-Object System.Drawing.Drawing2D.GraphicsPath
    $leaf1.AddBezier(144, 95, 88, 82, 68, 54, 54, 38)
    $leaf1.AddBezier(144, 95, 102, 104, 72, 91, 54, 38)
    $graphics.FillPath($greenBrush, $leaf1)

    $leaf2 = New-Object System.Drawing.Drawing2D.GraphicsPath
    $leaf2.AddBezier(150, 94, 122, 42, 136, 22, 156, 16)
    $leaf2.AddBezier(150, 94, 172, 52, 174, 30, 156, 16)
    $graphics.FillPath($limeBrush, $leaf2)

    $leaf3 = New-Object System.Drawing.Drawing2D.GraphicsPath
    $leaf3.AddBezier(154, 96, 202, 64, 218, 45, 226, 30)
    $leaf3.AddBezier(154, 96, 194, 102, 216, 76, 226, 30)
    $graphics.FillPath($greenBrush, $leaf3)

    $leaf4 = New-Object System.Drawing.Drawing2D.GraphicsPath
    $leaf4.AddBezier(146, 100, 98, 122, 74, 148, 58, 176)
    $leaf4.AddBezier(146, 100, 116, 150, 86, 168, 58, 176)
    $graphics.FillPath($limeBrush, $leaf4)

    $graphics.DrawArc($greenPen, 78, 74, 120, 116, 205, 118)
    $graphics.DrawArc($limePen, 92, 88, 104, 94, 210, 116)

    $graphics.FillEllipse($goldBrush, 126, 108, 42, 42)
    $graphics.FillEllipse((New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb(151, 94, 47))), 136, 118, 22, 22)
    $graphics.FillEllipse($goldBrush, 96, 124, 38, 38)
    $graphics.FillEllipse($goldBrush, 158, 126, 36, 36)

    $fontBrand = New-Object System.Drawing.Font "Arial", 54, ([System.Drawing.FontStyle]::Bold)
    $fontName = New-Object System.Drawing.Font "Arial", 30, ([System.Drawing.FontStyle]::Bold)
    $fontSuffix = New-Object System.Drawing.Font "Arial", 22, ([System.Drawing.FontStyle]::Regular)

    $graphics.DrawString($brand, $fontBrand, $textBrush, 270, 30)
    $graphics.DrawString($name, $fontName, $greenBrush, 276, 128)
    $graphics.DrawString($suffix, $fontSuffix, $mutedBrush, 278, 182)

    $tagPen = New-Object System.Drawing.Pen $gold, 5
    $graphics.DrawLine($tagPen, 278, 238, 690, 238)
    $graphics.DrawString("Savings | Farm Inputs | Harvest Growth", (New-Object System.Drawing.Font "Arial", 17, ([System.Drawing.FontStyle]::Bold)), $mutedBrush, 712, 222)

    $bitmap.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $graphics.Dispose()
    $bitmap.Dispose()
}

function New-Favicon {
    param([string]$Path)

    $bitmap = New-Object System.Drawing.Bitmap 256, 256
    $bitmap.SetResolution(144, 144)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.Clear([System.Drawing.Color]::Transparent)

    $green = [System.Drawing.Color]::FromArgb(22, 118, 64)
    $lime = [System.Drawing.Color]::FromArgb(148, 194, 70)
    $gold = [System.Drawing.Color]::FromArgb(242, 181, 66)
    $deep = [System.Drawing.Color]::FromArgb(27, 58, 36)

    $graphics.FillEllipse((New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb(236, 250, 226))), 16, 16, 224, 224)
    $graphics.DrawEllipse((New-Object System.Drawing.Pen $green, 9), 16, 16, 224, 224)
    $graphics.DrawBezier((New-Object System.Drawing.Pen ([System.Drawing.Color]::FromArgb(112, 75, 44), 13)), 128, 212, 124, 168, 134, 128, 148, 92)
    $graphics.FillPie((New-Object System.Drawing.SolidBrush $green), 48, 38, 145, 116, 188, 80)
    $graphics.FillPie((New-Object System.Drawing.SolidBrush $lime), 72, 20, 118, 128, 242, 82)
    $graphics.FillPie((New-Object System.Drawing.SolidBrush $green), 95, 36, 136, 116, 282, 82)
    $graphics.FillEllipse((New-Object System.Drawing.SolidBrush $gold), 96, 111, 54, 54)
    $graphics.FillEllipse((New-Object System.Drawing.SolidBrush $gold), 134, 118, 46, 46)
    $graphics.DrawString("N", (New-Object System.Drawing.Font "Arial", 62, ([System.Drawing.FontStyle]::Bold)), (New-Object System.Drawing.SolidBrush $deep), 76, 154)

    $bitmap.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $graphics.Dispose()
    $bitmap.Dispose()
}

New-LogoBitmap -Path (Join-Path $outDir "logo.png") -Dark $false
New-LogoBitmap -Path (Join-Path $outDir "logo_dark.png") -Dark $true
New-Favicon -Path (Join-Path $outDir "favicon.png")

[pscustomobject]@{
    Logo = Join-Path $outDir "logo.png"
    DarkLogo = Join-Path $outDir "logo_dark.png"
    Favicon = Join-Path $outDir "favicon.png"
}
