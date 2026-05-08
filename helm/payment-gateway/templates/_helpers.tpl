{{/*
_helpers.tpl — переиспользуемые фрагменты шаблонов.
Обращение: {{ include "payment-gateway.fullname" . }}
*/}}

{{/* Полное имя релиза */}}
{{- define "payment-gateway.fullname" -}}
{{- printf "%s-%s" .Release.Name .Chart.Name | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/* Общие labels — ставятся на все ресурсы */}}
{{- define "payment-gateway.labels" -}}
helm.sh/chart: {{ .Chart.Name }}-{{ .Chart.Version }}
app.kubernetes.io/name: {{ .Chart.Name }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/* Selector labels — используются в Deployment.spec.selector */}}
{{- define "payment-gateway.selectorLabels" -}}
app.kubernetes.io/name: {{ .Chart.Name }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/* Имя ServiceAccount */}}
{{- define "payment-gateway.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "payment-gateway.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/* DSN для PostgreSQL */}}
{{- define "payment-gateway.dbHost" -}}
{{- if .Values.postgresql.enabled }}
{{- printf "%s-postgresql" .Release.Name }}
{{- else }}
{{- .Values.externalDatabase.host }}
{{- end }}
{{- end }}

{{/* Host для Redis */}}
{{- define "payment-gateway.redisHost" -}}
{{- if .Values.redis.enabled }}
{{- printf "%s-redis-master" .Release.Name }}
{{- else }}
{{- .Values.externalRedis.host }}
{{- end }}
{{- end }}
