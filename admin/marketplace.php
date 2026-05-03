!-- Admin can post products --
form method=POST
  input type=text name=title placeholder=Product Title required
  textarea name=description placeholder=Descriptiontextarea
  input type=number name=price step=0.01 placeholder=Price (NGN) required
  select name=category
    option value=inputAgricultural Inputsoption
    option value=equipmentEquipmentoption
    option value=serviceServicesoption
    option value=trainingTrainingoption
  select
  button type=submitAdd Productbutton
form

php
 Save product (seller_id = 0 for admin)
if ($_POST) {
    $pdo-prepare(
        INSERT INTO marketplace_items (seller_id, title, description, price, category)
        VALUES (0, , , , )
    )-execute([
        $_POST['title'],
        $_POST['description'],
        $_POST['price'],
        $_POST['category']
    ]);
}
