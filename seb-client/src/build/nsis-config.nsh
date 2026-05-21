!macro preInit
  SetRegView 64
  StrCpy $INSTDIR "$PROGRAMFILES64\${PRODUCT_NAME}"
  SetRegView 32
!macroend

!macro customHeader
  BrandingText "Veritas Exam Portal"
  RequestExecutionLevel admin
!macroend

!macro customInstall
  CreateDirectory "$APPDATA\${PRODUCT_NAME}"
  IfFileExists "$APPDATA\${PRODUCT_NAME}\config.json" 0 +2
    Goto +3
  CopyFiles /SILENT "$INSTDIR\resources\config.json" "$APPDATA\${PRODUCT_NAME}\config.json"
!macroend
