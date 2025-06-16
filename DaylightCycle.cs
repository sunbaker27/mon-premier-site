using UnityEngine;

public class DaylightCycle : MonoBehaviour
{
    [Tooltip("Durée d'un jour complet en secondes")]
    public float secondesParJour = 120f; // Par défaut, 2 minutes pour un jour complet

    private void Update()
    {
        // Calculer l'angle de rotation basé sur le temps
        float angleDeRotation = (360f / secondesParJour) * Time.deltaTime;
        
        // Faire tourner la lumière autour de l'axe X
        transform.Rotate(Vector3.right, angleDeRotation);
    }
} 