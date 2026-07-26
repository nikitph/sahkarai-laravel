output "droplet_id" {
  description = "DigitalOcean droplet ID."
  value       = digitalocean_droplet.application.id
}

output "deployment_host" {
  description = "Stable reserved IP used for SSH and deployment."
  value       = digitalocean_reserved_ip.application.ip_address
}

output "app_host" {
  description = "Default sslip.io application hostname."
  value       = "app.${digitalocean_reserved_ip.application.ip_address}.sslip.io"
}

output "reverb_host" {
  description = "Default sslip.io Reverb hostname."
  value       = "ws.${digitalocean_reserved_ip.application.ip_address}.sslip.io"
}

output "spaces_bucket" {
  description = "Spaces bucket name when object storage is enabled."
  value       = var.create_spaces_bucket ? digitalocean_spaces_bucket.application[0].name : null
}
