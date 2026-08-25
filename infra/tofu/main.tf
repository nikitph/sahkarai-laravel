locals {
  tags = [
    var.project_name,
    var.environment,
    "managed-by-opentofu",
  ]
}

resource "digitalocean_project" "application" {
  name        = "${var.project_name}-${var.environment}"
  description = "${var.project_name} ${var.environment} infrastructure"
  purpose     = "Web Application"
  environment = title(var.environment)
}

resource "digitalocean_droplet" "application" {
  name       = var.droplet_name
  region     = var.region
  size       = var.droplet_size
  image      = var.droplet_image
  ssh_keys   = var.ssh_key_fingerprints
  ipv6       = true
  monitoring = true
  backups    = var.enable_droplet_backups
  tags       = local.tags

  lifecycle {
    prevent_destroy = true
  }
}

resource "digitalocean_reserved_ip" "application" {
  region = var.region
}

resource "digitalocean_reserved_ip_assignment" "application" {
  ip_address = digitalocean_reserved_ip.application.ip_address
  droplet_id = digitalocean_droplet.application.id
}

resource "digitalocean_firewall" "application" {
  name        = "${var.project_name}-${var.environment}"
  droplet_ids = [digitalocean_droplet.application.id]

  inbound_rule {
    protocol         = "tcp"
    port_range       = "22"
    source_addresses = var.ssh_allowed_cidrs
  }

  inbound_rule {
    protocol         = "tcp"
    port_range       = "80"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  inbound_rule {
    protocol         = "tcp"
    port_range       = "443"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }

  outbound_rule {
    protocol              = "tcp"
    port_range            = "1-65535"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }

  outbound_rule {
    protocol              = "udp"
    port_range            = "1-65535"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }

  outbound_rule {
    protocol              = "icmp"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }
}

resource "digitalocean_spaces_bucket" "application" {
  count         = var.create_spaces_bucket ? 1 : 0
  name          = var.spaces_bucket_name
  region        = var.region
  acl           = "private"
  force_destroy = false

  versioning {
    enabled = true
  }
}

resource "digitalocean_project_resources" "application" {
  project = digitalocean_project.application.id
  resources = concat(
    [digitalocean_droplet.application.urn],
    var.create_spaces_bucket ? [digitalocean_spaces_bucket.application[0].urn] : [],
  )
}
